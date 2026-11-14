<?php

use App\Actions\Learning\ComputeLessonAccess;
use App\Actions\Learning\LessonAccess;
use App\Actions\Learning\MoveEnrollmentToBatch;
use App\Actions\Learning\SetLessonUnlockOverride;
use App\Enums\CourseStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonUnlockOverride;
use App\Models\Module;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function access(): ComputeLessonAccess
{
    return app(ComputeLessonAccess::class);
}

afterEach(fn () => Carbon::setTestNow());

test('offset 0 opens at 00:00 on the batch start date (Asia/Jakarta)', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $s = dripSetup('2026-11-15');

    expect(access()->unlockAt($s['enrollment'], $s['lessons'][0])->format('Y-m-d H:i:s e'))->toBe('2026-11-15 00:00:00 Asia/Jakarta')
        ->and(access()->unlockAt($s['enrollment'], $s['lessons'][2])->format('Y-m-d H:i'))->toBe('2026-11-17 00:00');

    Carbon::setTestNow(Carbon::parse('2026-11-14 23:59:59', 'Asia/Jakarta'));
    expect(access()->isUnlocked($s['enrollment'], $s['lessons'][0]))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-11-15 00:00:00', 'Asia/Jakarta'));
    expect(access()->isUnlocked($s['enrollment'], $s['lessons'][0]))->toBeTrue()
        ->and(access()->isUnlocked($s['enrollment'], $s['lessons'][1]))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-11-15 23:59:59', 'Asia/Jakarta'));
    expect(access()->isUnlocked($s['enrollment'], $s['lessons'][1]))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-11-16 00:00:00', 'Asia/Jakarta'));
    expect(access()->isUnlocked($s['enrollment'], $s['lessons'][1]))->toBeTrue();
});

test('the unlock time is configurable', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    config(['pixora.learning.unlock_time' => '07:30']);
    $s = dripSetup('2026-11-15');

    expect(access()->unlockAt($s['enrollment'], $s['lessons'][1])->format('Y-m-d H:i'))->toBe('2026-11-16 07:30');
});

test('everything is locked before the batch starts except preview lessons', function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
    $s = dripSetup('2026-11-15');

    $all = access()->forEnrollment($s['enrollment'], collect([...$s['lessons'], $s['preview']]));

    expect(collect($s['lessons'])->every(fn (Lesson $lesson) => ! $all[$lesson->id]->unlocked))->toBeTrue()
        ->and($all[$s['preview']->id]->unlocked)->toBeTrue()
        ->and($all[$s['preview']->id]->source)->toBe(LessonAccess::SOURCE_PREVIEW);
});

test('late enrollees immediately see lessons that are already open', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 08:00:00', 'Asia/Jakarta'));
    $s = dripSetup('2026-11-15');
    $s['enrollment']->update(['enrolled_at' => now()]); // joined on day 3

    expect(access()->isUnlocked($s['enrollment'], $s['lessons'][0]))->toBeTrue()
        ->and(access()->isUnlocked($s['enrollment'], $s['lessons'][1]))->toBeTrue()
        ->and(access()->isUnlocked($s['enrollment'], $s['lessons'][2]))->toBeTrue();
});

test('override priority: enrollment > batch > default', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $s = dripSetup('2026-11-15');
    $lesson = $s['lessons'][2]; // default 17 Nov

    LessonUnlockOverride::create(['lesson_id' => $lesson->id, 'batch_id' => $s['batch']->id, 'unlock_at' => '2026-11-12 08:00:00']);
    expect(access()->access($s['enrollment'], $lesson))
        ->source->toBe(LessonAccess::SOURCE_BATCH)
        ->and(access()->unlockAt($s['enrollment'], $lesson)->format('Y-m-d H:i'))->toBe('2026-11-12 08:00');

    LessonUnlockOverride::create(['lesson_id' => $lesson->id, 'batch_id' => $s['batch']->id, 'enrollment_id' => $s['enrollment']->id, 'unlock_at' => '2026-11-11 06:00:00']);
    expect(access()->access($s['enrollment'], $lesson)->source)->toBe(LessonAccess::SOURCE_ENROLLMENT)
        ->and(access()->unlockAt($s['enrollment'], $lesson)->format('Y-m-d H:i'))->toBe('2026-11-11 06:00');

    // Another student in the batch only gets the batch override.
    $other = Enrollment::factory()->create(['course_id' => $s['course']->id, 'batch_id' => $s['batch']->id]);
    expect(access()->unlockAt($other, $lesson)->format('Y-m-d H:i'))->toBe('2026-11-12 08:00');
});

test('overrides of other batches are ignored', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $s = dripSetup('2026-11-15');
    $otherBatch = Batch::factory()->for($s['course'])->startingOn('2026-12-01')->create();
    LessonUnlockOverride::create(['lesson_id' => $s['lessons'][0]->id, 'batch_id' => $otherBatch->id, 'unlock_at' => '2026-11-01 00:00:00']);

    expect(access()->access($s['enrollment'], $s['lessons'][0])->source)->toBe(LessonAccess::SOURCE_DEFAULT);
});

test('overrides can open early or move unopened lessons, but never lock an open lesson again', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Asia/Jakarta')); // day 2
    $s = dripSetup('2026-11-15');
    $set = app(SetLessonUnlockOverride::class);
    $admin = User::factory()->create();

    // Open day-3 lesson early, then move it later again (still not opened by then? it is now open).
    $set->set($s['lessons'][2], $s['batch'], null, now()->addHours(2), $admin); // not open yet → moved within future
    expect(access()->isUnlocked($s['enrollment'], $s['lessons'][2]))->toBeFalse();
    $set->set($s['lessons'][2], $s['batch'], null, now()->subMinute(), $admin); // open now
    expect(access()->isUnlocked($s['enrollment'], $s['lessons'][2]))->toBeTrue();

    // Re-locking an open lesson (batch or one student) is rejected.
    expect(fn () => $set->set($s['lessons'][2], $s['batch'], null, now()->addDay(), $admin))
        ->toThrow(ValidationException::class, 'tidak bisa dikunci kembali');
    expect(fn () => $set->set($s['lessons'][0], $s['batch'], $s['enrollment'], now()->addDay(), $admin))
        ->toThrow(ValidationException::class);

    // Resetting the early-open override would lock it again → rejected; nothing changes.
    expect(fn () => $set->reset($s['lessons'][2], $s['batch'], null))->toThrow(ValidationException::class);
    expect(access()->isUnlocked($s['enrollment'], $s['lessons'][2]))->toBeTrue();
});

test('the batch-wide override stays unique even with NULL enrollment', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $s = dripSetup('2026-11-15');
    $set = app(SetLessonUnlockOverride::class);
    $admin = User::factory()->create();

    $set->set($s['lessons'][2], $s['batch'], null, Carbon::parse('2026-11-13 08:00'), $admin);
    $set->set($s['lessons'][2], $s['batch'], null, Carbon::parse('2026-11-14 08:00'), $admin);
    $set->set($s['lessons'][2], $s['batch'], $s['enrollment'], Carbon::parse('2026-11-12 08:00'), $admin);

    expect(LessonUnlockOverride::count())->toBe(2)
        ->and(LessonUnlockOverride::whereNull('enrollment_id')->sole()->unlock_at->format('Y-m-d H:i'))->toBe('2026-11-14 08:00');

    // The database also refuses a second batch-wide row.
    expect(fn () => LessonUnlockOverride::create(['lesson_id' => $s['lessons'][2]->id, 'batch_id' => $s['batch']->id, 'unlock_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('non-drip courses open every published lesson at enrollment', function () {
    $course = Course::factory()->published()->create(['drip_enabled' => false]);
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create(['course_id' => $course->id, 'drip_day_offset' => 30]);
    $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'batch_id' => null]);

    expect(access()->access($enrollment, $lesson))
        ->unlocked->toBeTrue()
        ->source->toBe(LessonAccess::SOURCE_IMMEDIATE)
        ->and(access()->canViewLesson($enrollment, $lesson))->toBeTrue();
});

test('enrollment and course state gate access', function (Closure $mutate, bool $allowed) {
    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00', 'Asia/Jakarta'));
    $s = dripSetup('2026-11-15');
    $mutate($s);

    expect(access()->canViewLesson($s['enrollment']->fresh(), $s['lessons'][0]))->toBe($allowed);
})->with([
    'active' => [fn () => null, true],
    'completed' => [fn ($s) => $s['enrollment']->update(['status' => 'completed']), true],
    'expired status' => [fn ($s) => $s['enrollment']->update(['status' => 'expired']), false],
    'revoked' => [fn ($s) => $s['enrollment']->update(['status' => 'revoked']), false],
    'past expires_at' => [fn ($s) => $s['enrollment']->update(['expires_at' => now()->subMinute()]), false],
    'course archived (still allowed)' => [fn ($s) => $s['course']->update(['status' => CourseStatus::Archived]), true],
    'course back to draft' => [fn ($s) => $s['course']->update(['status' => CourseStatus::Draft]), false],
    'lesson unpublished' => [fn ($s) => $s['lessons'][0]->update(['status' => 'draft']), false],
    'module deleted' => [fn ($s) => $s['lessons'][0]->module->delete(), false],
]);

test('a lesson of another course is never viewable', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00', 'Asia/Jakarta'));
    $s = dripSetup('2026-11-15');
    $foreign = Lesson::factory()->create(['drip_day_offset' => 0]);

    expect(access()->canViewLesson($s['enrollment'], $foreign))->toBeFalse();
});

test('forEnrollment loads overrides in a single query', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $s = dripSetup('2026-11-15');
    $lessons = collect([...$s['lessons'], $s['preview']]);
    $enrollment = $s['enrollment']->load(['course', 'batch']);

    DB::enableQueryLog();
    access()->forEnrollment($enrollment, $lessons);
    expect(DB::getQueryLog())->toHaveCount(1);
});

test('moving an enrollment to a later batch keeps already-open lessons open', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00', 'Asia/Jakarta')); // day 3 of first batch
    $s = dripSetup('2026-11-15');
    $later = Batch::factory()->for($s['course'])->startingOn('2026-12-01')->create();
    LessonUnlockOverride::create(['lesson_id' => $s['lessons'][2]->id, 'batch_id' => $s['batch']->id, 'enrollment_id' => $s['enrollment']->id, 'unlock_at' => now()->subDay()]);

    app(MoveEnrollmentToBatch::class)->handle($s['enrollment'], $later, User::factory()->create());

    $enrollment = $s['enrollment']->fresh();
    expect($enrollment->batch_id)->toBe($later->id)
        ->and(LessonUnlockOverride::where('batch_id', $s['batch']->id)->where('enrollment_id', $enrollment->id)->count())->toBe(0);

    foreach ($s['lessons'] as $lesson) {
        expect(access()->isUnlocked($enrollment, $lesson))->toBeTrue();
    }
});
