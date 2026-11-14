<?php

use App\Actions\Curriculum\DeleteModule;
use App\Actions\Learning\MarkLessonCompleted;
use App\Actions\Learning\RecalculateEnrollmentProgress;
use App\Actions\Learning\UnmarkLessonCompleted;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Jobs\RecalculateCourseProgress;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

afterEach(fn () => Carbon::setTestNow());

/**
 * Non-drip course with $count published lessons and an active enrollment.
 *
 * @return array{course: Course, module: Module, lessons: Collection<int, Lesson>, enrollment: Enrollment}
 */
function openCourse(int $count = 3): array
{
    $course = Course::factory()->published()->create(['drip_enabled' => false]);
    $module = Module::factory()->for($course)->create();
    $lessons = Lesson::factory()->count($count)->for($module)->published()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

    return compact('course', 'module', 'lessons', 'enrollment');
}

test('progress is completed published lessons over published lessons, rounded down', function () {
    $c = openCourse(3);
    $mark = app(MarkLessonCompleted::class);

    expect($mark->handle($c['enrollment'], $c['lessons'][0]))->toBe(33)
        ->and($mark->handle($c['enrollment'], $c['lessons'][1]))->toBe(66)
        ->and($mark->handle($c['enrollment'], $c['lessons'][2]))->toBe(100)
        ->and($c['enrollment']->fresh()->progress_percent)->toBe(100)
        // Reaching 100% does not complete the enrollment (Phase 6 decides).
        ->and($c['enrollment']->fresh()->status)->toBe(EnrollmentStatus::Active);
});

test('marking and unmarking are idempotent', function () {
    $c = openCourse(2);
    $mark = app(MarkLessonCompleted::class);
    $unmark = app(UnmarkLessonCompleted::class);

    $mark->handle($c['enrollment'], $c['lessons'][0]);
    $firstCompletedAt = LessonProgress::sole()->completed_at;
    Carbon::setTestNow(now()->addHour());
    $mark->handle($c['enrollment'], $c['lessons'][0]);

    expect(LessonProgress::count())->toBe(1)
        ->and(LessonProgress::sole()->completed_at->equalTo($firstCompletedAt))->toBeTrue()
        ->and($c['enrollment']->fresh()->progress_percent)->toBe(50);

    $unmark->handle($c['enrollment'], $c['lessons'][0]);
    $unmark->handle($c['enrollment'], $c['lessons'][0]);

    expect(LessonProgress::sole()->status->value)->toBe('in_progress')
        ->and($c['enrollment']->fresh()->progress_percent)->toBe(0);
});

test('an empty course has 0% progress', function () {
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'progress_percent' => 40]);

    expect(app(RecalculateEnrollmentProgress::class)->handle($enrollment))->toBe(0);
});

test('only open lessons can be marked completed', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'Asia/Jakarta'));
    $s = dripSetup('2026-11-15');

    app(MarkLessonCompleted::class)->handle($s['enrollment'], $s['lessons'][0]);

    expect(fn () => app(MarkLessonCompleted::class)->handle($s['enrollment'], $s['lessons'][1]))
        ->toThrow(ValidationException::class, 'belum terbuka');
    expect(fn () => app(MarkLessonCompleted::class)->handle($s['enrollment'], $s['draft']))->toThrow(ValidationException::class);
});

test('progress is recalculated when lessons are published, unpublished or deleted', function () {
    $c = openCourse(4);
    app(MarkLessonCompleted::class)->handle($c['enrollment'], $c['lessons'][0]);
    app(MarkLessonCompleted::class)->handle($c['enrollment'], $c['lessons'][1]);
    expect($c['enrollment']->fresh()->progress_percent)->toBe(50);

    $c['lessons'][3]->update(['status' => LessonStatus::Draft]);      // 2 of 3
    expect($c['enrollment']->fresh()->progress_percent)->toBe(66);

    $c['lessons'][2]->delete();                                       // 2 of 2
    expect($c['enrollment']->fresh()->progress_percent)->toBe(100);

    Lesson::factory()->for($c['module'])->published()->create(['course_id' => $c['course']->id]); // 2 of 3
    expect($c['enrollment']->fresh()->progress_percent)->toBe(66);

    app(DeleteModule::class)->handle($c['module']);                   // nothing published left
    expect($c['enrollment']->fresh()->progress_percent)->toBe(0);
});

test('lesson changes dispatch one queued recalculation per course, only when someone is enrolled', function () {
    Queue::fake();
    $c = openCourse(2);

    $c['lessons'][0]->update(['status' => LessonStatus::Draft]);
    $c['lessons'][1]->update(['title' => 'Judul baru']); // not a status change

    Queue::assertPushed(RecalculateCourseProgress::class, 1);
    Queue::assertPushed(RecalculateCourseProgress::class, fn ($job) => $job->courseId === $c['course']->id);

    Queue::fake();
    $lonely = Lesson::factory()->published()->create();
    $lonely->update(['status' => LessonStatus::Draft]);
    Queue::assertNothingPushed();
});
