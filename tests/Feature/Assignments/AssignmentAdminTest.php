<?php

use App\Enums\CourseInstructorRole;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Submission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->course = publishableCourse(['drip_enabled' => false]);
    $this->lesson = $this->course->lessons()->firstOrFail();
});

function assignmentPayload(array $overrides = []): array
{
    return [
        'title' => 'Sketsa logo',
        'instructions_html' => '<p>Unggah <strong>3 sketsa</strong>.</p><script>alert(1)</script><img src=x onerror=alert(1)>',
        'due_offset_days' => 2,
        'max_images' => 3,
        'is_required' => true,
        'status' => 'published',
        ...$overrides,
    ];
}

test('admins add an assignment to a lesson with sanitised instructions', function () {
    actingAsRole(Role::Admin);

    $this->post(route('admin.assignments.store', $this->course), assignmentPayload(['lesson_id' => $this->lesson->id]))->assertSessionHasNoErrors();

    $assignment = Assignment::sole();
    expect($assignment->lesson_id)->toBe($this->lesson->id)
        ->and($assignment->is_final_project)->toBeFalse()
        ->and($assignment->is_required)->toBeTrue()
        ->and($assignment->instructions_html)->toContain('<strong>3 sketsa</strong>')
        ->and($assignment->instructions_html)->not->toContain('script')
        ->and($assignment->instructions_html)->not->toContain('onerror');

    $this->get(route('admin.courses.curriculum', $this->course))
        ->assertInertia(fn (Assert $page) => $page->where('modules.0.lessons.0.assignment.title', 'Sketsa logo')->where('finalProject', null));
});

test('one assignment per lesson and one final project per course', function () {
    actingAsRole(Role::Admin);

    $this->post(route('admin.assignments.store', $this->course), assignmentPayload(['lesson_id' => $this->lesson->id]))->assertSessionHasNoErrors();
    $this->post(route('admin.assignments.store', $this->course), assignmentPayload(['lesson_id' => $this->lesson->id]))->assertSessionHasErrors(['title' => 'Lesson ini sudah memiliki tugas.']);

    $this->post(route('admin.assignments.store', $this->course), assignmentPayload(['title' => 'Proyek akhir']))->assertSessionHasNoErrors();
    $this->post(route('admin.assignments.store', $this->course), assignmentPayload())->assertSessionHasErrors(['title' => 'Kursus ini sudah memiliki proyek akhir.']);

    expect(Assignment::whereNull('lesson_id')->sole()->is_final_project)->toBeTrue();

    // A lesson of another course is refused.
    $other = publishableCourse()->lessons()->firstOrFail();
    $this->post(route('admin.assignments.store', $this->course), assignmentPayload(['lesson_id' => $other->id]))->assertSessionHasErrors('lesson_id');
});

test('a deleted assignment slot is restored instead of duplicated', function () {
    actingAsRole(Role::Admin);
    $first = Assignment::factory()->forLesson($this->lesson)->create();

    $this->delete(route('admin.assignments.destroy', $first))->assertSessionHasNoErrors();
    $this->post(route('admin.assignments.store', $this->course), assignmentPayload(['lesson_id' => $this->lesson->id, 'title' => 'Baru']))->assertSessionHasNoErrors();

    expect(Assignment::withTrashed()->count())->toBe(1)->and(Assignment::sole()->title)->toBe('Baru');
});

test('assignments with submissions cannot be deleted', function () {
    actingAsRole(Role::Admin);
    $assignment = Assignment::factory()->forLesson($this->lesson)->create();
    Submission::factory()->create(['assignment_id' => $assignment->id]);

    $this->delete(route('admin.assignments.destroy', $assignment))->assertSessionHasErrors('assignment');
    expect($assignment->refresh()->trashed())->toBeFalse();
});

test('validation: offset, image limit from config, status', function () {
    actingAsRole(Role::Admin);
    config(['pixora.assignments.max_images' => 5]);

    $this->post(route('admin.assignments.store', $this->course), assignmentPayload(['due_offset_days' => -1, 'max_images' => 6, 'status' => 'archived', 'title' => '']))
        ->assertSessionHasErrors(['due_offset_days', 'max_images', 'status', 'title']);
});

test('only content managers manage assignments', function (Role $role) {
    $user = actingAsRole($role);
    $this->course->instructors()->syncWithoutDetaching([$user->id => ['role' => CourseInstructorRole::Assistant->value]]);
    $assignment = Assignment::factory()->forLesson($this->lesson)->create();

    $this->post(route('admin.assignments.store', $this->course), assignmentPayload())->assertForbidden();
    $this->put(route('admin.assignments.update', $assignment), assignmentPayload())->assertForbidden();
    $this->delete(route('admin.assignments.destroy', $assignment))->assertForbidden();
})->with([Role::Staff, Role::Instructor, Role::Student]);
