<?php

use App\Enums\CourseInstructorRole;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\LessonUnlockOverride;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Asia/Jakarta'))); // day 2

afterEach(fn () => Carbon::setTestNow());

/**
 * dripSetup() plus an instructor assigned to the course.
 *
 * @return array<string, mixed>
 */
function classSetup(): array
{
    $s = dripSetup('2026-11-15');
    $s['instructor'] = instructorUser();
    $s['course']->instructors()->attach($s['instructor'], ['role' => CourseInstructorRole::Lead->value]);

    return $s;
}

test('assigned instructors see batches, students with progress and the schedule', function () {
    $s = classSetup();
    $s['enrollment']->update(['progress_percent' => 40]);

    $this->actingAs($s['instructor'])->get(route('classes.batches', $s['course']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('classes/batches')->where('batches.0.seats_taken', 1));

    $this->actingAs($s['instructor'])->get(route('classes.students', [$s['course'], $s['batch']]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('classes/students')
            ->where('students.total', 1)
            ->where('students.data.0.email', $s['student']->email)
            ->where('students.data.0.progress_percent', 40)
            ->where('otherBatches', []));

    $this->actingAs($s['instructor'])->get(route('classes.schedule', [$s['course'], $s['batch']]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('classes/schedule')
            ->where('modules.0.lessons.0.is_open', true)
            ->where('modules.0.lessons.2.is_open', false)
            ->where('modules.0.lessons.2.default_unlock_at', '2026-11-17T00:00:00+07:00')
            ->where('modules.0.lessons.2.override_unlock_at', null));
});

test('the student list is searchable and paginated', function () {
    $s = classSetup();
    Enrollment::factory()->count(22)->create(['course_id' => $s['course']->id, 'batch_id' => $s['batch']->id]);
    $s['student']->update(['name' => 'Zulaikha Unik']);

    $this->actingAs($s['instructor'])->get(route('classes.students', [$s['course'], $s['batch']]))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 23)->has('students.data', 20));

    $this->actingAs($s['instructor'])->get(route('classes.students', [$s['course'], $s['batch'], 'q' => 'zulaikha']))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 1)->where('filters.q', 'zulaikha'));

    // LIKE wildcards are escaped.
    $this->actingAs($s['instructor'])->get(route('classes.students', [$s['course'], $s['batch'], 'q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 0));
});

test('other instructors, staff and students cannot run the class', function (string $who) {
    $s = classSetup();
    $user = match ($who) {
        'other instructor' => instructorUser(),
        'staff' => User::factory()->create()->assignRole(Role::Staff->value),
        'student' => $s['student'],
    };

    $this->actingAs($user)->get(route('classes.batches', $s['course']))->assertNotFound();
    $this->actingAs($user)->get(route('classes.students', [$s['course'], $s['batch']]))->assertNotFound();
    $this->actingAs($user)->post(route('classes.schedule.set', [$s['course'], $s['batch'], $s['lessons'][2]]), ['unlock_at' => '2026-11-16 12:00'])->assertNotFound();

    expect(LessonUnlockOverride::count())->toBe(0);
})->with(['other instructor', 'staff', 'student']);

test('admins can run any class', function () {
    $s = classSetup();
    actingAsRole(Role::Admin);

    $this->get(route('classes.schedule', [$s['course'], $s['batch']]))->assertOk();
});

test('an instructor opens a lesson early for the whole batch and resets it', function () {
    $s = classSetup();
    $lesson = $s['lessons'][2];

    $this->actingAs($s['instructor'])
        ->post(route('classes.schedule.set', [$s['course'], $s['batch'], $lesson]), ['unlock_at' => '2026-11-16T09:00'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $override = LessonUnlockOverride::sole();
    expect($override->enrollment_id)->toBeNull()
        ->and($override->created_by)->toBe($s['instructor']->id);

    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $lesson]))
        ->assertInertia(fn (Assert $page) => $page->where('lesson.status', 'unlocked'));

    // Already open now → resetting would re-lock it.
    $this->actingAs($s['instructor'])
        ->delete(route('classes.schedule.reset', [$s['course'], $s['batch'], $lesson]))
        ->assertSessionHasErrors('unlock_at');
});

test('a future override can be reset to the default schedule', function () {
    $s = classSetup();
    $lesson = $s['lessons'][2];

    $this->actingAs($s['instructor'])->post(route('classes.schedule.set', [$s['course'], $s['batch'], $lesson]), ['unlock_at' => '2026-11-20T00:00']);
    expect(LessonUnlockOverride::count())->toBe(1);

    $this->actingAs($s['instructor'])->delete(route('classes.schedule.reset', [$s['course'], $s['batch'], $lesson]))->assertSessionHasNoErrors();
    expect(LessonUnlockOverride::count())->toBe(0);
});

test('an open lesson cannot be pushed into the future', function () {
    $s = classSetup();

    $this->actingAs($s['instructor'])
        ->post(route('classes.schedule.set', [$s['course'], $s['batch'], $s['lessons'][0]]), ['unlock_at' => '2026-11-25T00:00'])
        ->assertSessionHasErrors('unlock_at');

    expect(LessonUnlockOverride::count())->toBe(0);
});

test('a per-student override only affects that student', function () {
    $s = classSetup();
    $lesson = $s['lessons'][2];
    $classmate = Enrollment::factory()->create(['course_id' => $s['course']->id, 'batch_id' => $s['batch']->id]);

    $this->actingAs($s['instructor'])
        ->post(route('classes.student-schedule.set', [$s['course'], $s['batch'], $s['enrollment'], $lesson]), ['unlock_at' => '2026-11-16T08:00'])
        ->assertSessionHasNoErrors();

    expect(LessonUnlockOverride::sole()->enrollment_id)->toBe($s['enrollment']->id);

    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $lesson]))
        ->assertInertia(fn (Assert $page) => $page->where('lesson.status', 'unlocked'));
    $this->actingAs($classmate->user)->get(route('learn.lesson', [$s['course'], $lesson]))
        ->assertInertia(fn (Assert $page) => $page->where('lesson.status', 'locked')->where('content', null));

    $this->actingAs($s['instructor'])->get(route('classes.student-schedule', [$s['course'], $s['batch'], $s['enrollment']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('student.id', $s['enrollment']->id)
            ->where('modules.0.lessons.2.override_unlock_at', '2026-11-16T08:00:00+07:00')
            ->where('modules.0.lessons.2.is_open', true));
});

test('mismatched URLs are not found', function () {
    $s = classSetup();
    $otherBatch = Batch::factory()->upcoming()->create();
    $otherStudent = Enrollment::factory()->create(['course_id' => $s['course']->id, 'batch_id' => Batch::factory()->for($s['course'])->upcoming()->create()->id]);
    $foreignLesson = dripSetup()['lessons'][0];

    $this->actingAs($s['instructor'])->get(route('classes.students', [$s['course'], $otherBatch]))->assertNotFound();
    $this->actingAs($s['instructor'])->get(route('classes.student-schedule', [$s['course'], $s['batch'], $otherStudent]))->assertNotFound();
    $this->actingAs($s['instructor'])->post(route('classes.schedule.set', [$s['course'], $s['batch'], $foreignLesson]), ['unlock_at' => '2026-11-16T09:00'])->assertNotFound();
});

test('only admins move a student to another batch', function () {
    $s = classSetup();
    $target = Batch::factory()->for($s['course'])->upcoming()->create();

    $this->actingAs($s['instructor'])
        ->post(route('classes.move', [$s['course'], $s['batch'], $s['enrollment']]), ['batch_id' => $target->id])
        ->assertForbidden();

    actingAsRole(Role::Admin);
    $this->get(route('classes.students', [$s['course'], $s['batch']]))
        ->assertInertia(fn (Assert $page) => $page->where('otherBatches.0.id', $target->id));

    $this->post(route('classes.move', [$s['course'], $s['batch'], $s['enrollment']]), ['batch_id' => $target->id])->assertRedirect();

    expect($s['enrollment']->refresh()->batch_id)->toBe($target->id);

    // Lessons already open in the old batch stay open in the (later) new batch.
    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $s['lessons'][1]]))
        ->assertInertia(fn (Assert $page) => $page->where('lesson.status', 'unlocked'));
});
