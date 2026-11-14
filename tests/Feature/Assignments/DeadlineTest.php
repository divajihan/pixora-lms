<?php

use App\Actions\Assignments\AssignmentAvailability;
use App\Actions\Assignments\AssignmentDeadline;
use App\Actions\Assignments\ComputeAssignmentDeadline;
use App\Actions\Assignments\SetDeadlineOverride;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonUnlockOverride;
use App\Models\Module;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function deadlines(): ComputeAssignmentDeadline
{
    return app(ComputeAssignmentDeadline::class);
}

function wib(string $time): Carbon
{
    return Carbon::parse($time, 'Asia/Jakarta');
}

beforeEach(fn () => Carbon::setTestNow(wib('2026-11-14 10:00:00')));

afterEach(fn () => Carbon::setTestNow());

test('a lesson assignment is due at 23:59 on the lesson opening day + offset (per batch)', function () {
    $s = dripSetup('2026-11-15');
    $assignment = Assignment::factory()->forLesson($s['lessons'][1])->create(['due_offset_days' => 2]); // opens 16 Nov

    $deadline = deadlines()->for($assignment, $s['enrollment']);

    expect($deadline->reference->format('Y-m-d H:i'))->toBe('2026-11-16 00:00')
        ->and($deadline->effective->format('Y-m-d H:i:s e'))->toBe('2026-11-18 23:59:59 Asia/Jakarta')
        ->and($deadline->source)->toBe(AssignmentDeadline::SOURCE_DEFAULT);

    // Another batch of the same course has its own reference date.
    $later = Batch::factory()->for($s['course'])->startingOn('2026-12-01')->create();
    $other = Enrollment::factory()->create(['course_id' => $s['course']->id, 'batch_id' => $later->id]);
    expect(deadlines()->for($assignment, $other)->effective->format('Y-m-d H:i'))->toBe('2026-12-04 23:59');
});

test('a batch unlock override moves the reference date; a per-student unlock does not', function () {
    $s = dripSetup('2026-11-15');
    $lesson = $s['lessons'][2]; // opens 17 Nov
    $assignment = Assignment::factory()->forLesson($lesson)->create(['due_offset_days' => 0]);

    LessonUnlockOverride::create(['lesson_id' => $lesson->id, 'batch_id' => $s['batch']->id, 'enrollment_id' => $s['enrollment']->id, 'unlock_at' => wib('2026-11-15 08:00')]);
    expect(deadlines()->for($assignment, $s['enrollment'])->effective->format('Y-m-d H:i'))->toBe('2026-11-17 23:59');

    LessonUnlockOverride::create(['lesson_id' => $lesson->id, 'batch_id' => $s['batch']->id, 'enrollment_id' => null, 'unlock_at' => wib('2026-11-20 00:00')]);
    expect(deadlines()->for($assignment, $s['enrollment'])->effective->format('Y-m-d H:i'))->toBe('2026-11-20 23:59');
});

test('a final project is due after the batch end date', function () {
    $s = dripSetup('2026-11-15'); // batch ends 27 Nov
    $project = Assignment::factory()->create(['course_id' => $s['course']->id, 'due_offset_days' => 3]);

    expect($project->is_final_project)->toBeTrue()
        ->and(deadlines()->for($project, $s['enrollment'])->effective->format('Y-m-d H:i'))->toBe('2026-11-30 23:59');
});

test('non-drip courses count from the enrollment date', function () {
    $course = Course::factory()->published()->create(['drip_enabled' => false]);
    $lesson = Lesson::factory()->for(Module::factory()->for($course))->published()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'enrolled_at' => wib('2026-11-10 21:30')]);

    $lessonTask = Assignment::factory()->forLesson($lesson)->create(['due_offset_days' => 3]);
    $project = Assignment::factory()->create(['course_id' => $course->id, 'due_offset_days' => 7]);

    expect(deadlines()->for($lessonTask, $enrollment)->effective->format('Y-m-d H:i'))->toBe('2026-11-13 23:59')
        ->and(deadlines()->for($project, $enrollment)->effective->format('Y-m-d H:i'))->toBe('2026-11-17 23:59');
});

test('the deadline ends at 23:59:59 — 00:00 the next day is past and late', function () {
    $s = dripSetup('2026-11-15');
    $assignment = Assignment::factory()->forLesson($s['lessons'][0])->create(['due_offset_days' => 0]); // due 15 Nov 23:59:59

    Carbon::setTestNow(wib('2026-11-15 23:59:59'));
    $deadline = deadlines()->for($assignment, $s['enrollment']);
    expect($deadline->hasPassed())->toBeFalse()
        ->and($deadline->isLate(now()))->toBeFalse();

    Carbon::setTestNow(wib('2026-11-16 00:00:00'));
    expect($deadline->hasPassed())->toBeTrue()
        ->and($deadline->isLate(now()))->toBeTrue();
});

test('the due time is configurable', function () {
    config(['pixora.assignments.due_time' => '17:00']);
    $s = dripSetup('2026-11-15');
    $assignment = Assignment::factory()->forLesson($s['lessons'][0])->create(['due_offset_days' => 1]);

    expect(deadlines()->for($assignment, $s['enrollment'])->effective->format('Y-m-d H:i:s'))->toBe('2026-11-16 17:00:59');
});

test('overrides: enrollment beats batch beats default; reset goes back', function () {
    $s = dripSetup('2026-11-15');
    $assignment = Assignment::factory()->forLesson($s['lessons'][0])->create(['due_offset_days' => 1]); // default 16 Nov
    $admin = actingAsRole(Role::Admin);
    $set = app(SetDeadlineOverride::class);

    $set->set($assignment, $s['batch'], null, wib('2026-11-20 23:59:59'), $admin);
    expect(deadlines()->for($assignment, $s['enrollment']))
        ->source->toBe(AssignmentDeadline::SOURCE_BATCH)
        ->effective->format('Y-m-d')->toBe('2026-11-20');

    $set->set($assignment, null, $s['enrollment'], wib('2026-11-25 12:00'), $admin);
    expect(deadlines()->for($assignment, $s['enrollment']))
        ->source->toBe(AssignmentDeadline::SOURCE_ENROLLMENT)
        ->effective->format('Y-m-d H:i')->toBe('2026-11-25 12:00');

    // Updating the same scope keeps one row.
    $set->set($assignment, null, $s['enrollment'], wib('2026-11-26 12:00'), $admin);
    expect($assignment->deadlineOverrides()->count())->toBe(2);

    $set->reset($assignment, null, $s['enrollment']);
    expect(deadlines()->for($assignment, $s['enrollment'])->source)->toBe(AssignmentDeadline::SOURCE_BATCH);

    $set->reset($assignment, $s['batch'], null);
    expect(deadlines()->for($assignment, $s['enrollment']))
        ->source->toBe(AssignmentDeadline::SOURCE_DEFAULT)
        ->effective->format('Y-m-d')->toBe('2026-11-16');
});

test('a new override must be in the future and in the same course', function () {
    $s = dripSetup('2026-11-15');
    $assignment = Assignment::factory()->forLesson($s['lessons'][0])->create();
    $admin = actingAsRole(Role::Admin);

    expect(fn () => app(SetDeadlineOverride::class)->set($assignment, $s['batch'], null, now()->subMinute(), $admin))
        ->toThrow(ValidationException::class, 'Tenggat baru harus di masa depan.');

    $foreign = Batch::factory()->create();
    expect(fn () => app(SetDeadlineOverride::class)->set($assignment, $foreign, null, now()->addDay(), $admin))
        ->toThrow(ValidationException::class);
});

test('lateness is computed dynamically and an extension fixes it', function () {
    $s = dripSetup('2026-11-15');
    $assignment = Assignment::factory()->forLesson($s['lessons'][0])->create(['due_offset_days' => 0]); // due 15 Nov 23:59:59
    $submittedAt = wib('2026-11-16 08:00');

    expect(deadlines()->for($assignment, $s['enrollment'])->isLate($submittedAt))->toBeTrue();

    Carbon::setTestNow(wib('2026-11-16 09:00'));
    app(SetDeadlineOverride::class)->set($assignment, null, $s['enrollment'], wib('2026-11-17 23:59'), actingAsRole(Role::Admin));

    expect(deadlines()->for($assignment, $s['enrollment'])->isLate($submittedAt))->toBeFalse();
});

test('bulk deadlines use a constant number of queries', function () {
    $s = dripSetup('2026-11-15');
    collect($s['lessons'])->each(fn (Lesson $lesson) => Assignment::factory()->forLesson($lesson)->create());
    $assignments = Assignment::with('lesson')->get();
    $enrollments = Enrollment::factory()->count(5)->create(['course_id' => $s['course']->id, 'batch_id' => $s['batch']->id])->push($s['enrollment']);
    $enrollments->load('batch', 'course');

    $pairs = $assignments->crossJoin($enrollments)->values();

    DB::enableQueryLog();
    $result = deadlines()->many($pairs);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($result)->toHaveCount(18)->and($queries)->toBe(2);
});

test('assignments open with their lesson; final projects when the batch starts', function () {
    $s = dripSetup('2026-11-15');
    $availability = app(AssignmentAvailability::class);
    $dayTwo = Assignment::factory()->forLesson($s['lessons'][1])->create();
    $project = Assignment::factory()->create(['course_id' => $s['course']->id]);
    $draft = Assignment::factory()->forLesson($s['lessons'][0])->draft()->create();

    expect($availability->opensAt($s['enrollment'], $dayTwo)->format('Y-m-d H:i'))->toBe('2026-11-16 00:00')
        ->and($availability->opensAt($s['enrollment'], $project)->format('Y-m-d'))->toBe('2026-11-15')
        ->and($availability->isAvailable($s['enrollment'], $draft))->toBeFalse();

    Carbon::setTestNow(wib('2026-11-16 00:00:01'));
    expect($availability->isAvailable($s['enrollment'], $dayTwo))->toBeTrue()
        ->and($availability->isAvailable($s['enrollment'], $project))->toBeTrue();

    $s['enrollment']->update(['status' => 'revoked']);
    expect($availability->isAvailable($s['enrollment']->refresh(), $dayTwo))->toBeFalse();
});
