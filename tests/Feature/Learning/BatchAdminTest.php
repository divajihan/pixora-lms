<?php

use App\Enums\CourseInstructorRole;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Carbon::setTestNow('2026-11-14 10:00:00'));

afterEach(fn () => Carbon::setTestNow());

function batchPayload(array $overrides = []): array
{
    return [
        'name' => 'Batch 44',
        'starts_on' => '2026-11-15',
        'ends_on' => '2026-11-27',
        'registration_closes_on' => '',
        'capacity' => '',
        'is_published' => true,
        ...$overrides,
    ];
}

test('admins list, create and update batches of a drip course', function () {
    $course = Course::factory()->create(['drip_enabled' => true]);
    actingAsRole(Role::Admin);

    $this->post(route('admin.batches.store', $course), batchPayload(['capacity' => '30']))->assertRedirect();

    $batch = Batch::sole();
    expect($batch->name)->toBe('Batch 44')
        ->and($batch->starts_on->toDateString())->toBe('2026-11-15')
        ->and($batch->capacity)->toBe(30)
        ->and($batch->registration_closes_on)->toBeNull();

    $this->put(route('admin.batches.update', $batch), batchPayload(['name' => 'Batch 44A', 'ends_on' => '2026-11-30']))->assertRedirect();
    expect($batch->refresh()->name)->toBe('Batch 44A')->and($batch->capacity)->toBeNull();

    $this->get(route('admin.batches.index', $course))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/courses/batches')
            ->where('batches.0.name', 'Batch 44A')
            ->where('batches.0.status', 'upcoming')
            ->where('batches.0.seats_taken', 0));
});

test('batch dates are validated', function (array $payload, string $field) {
    $course = Course::factory()->create(['drip_enabled' => true]);
    actingAsRole(Role::Admin);

    $this->post(route('admin.batches.store', $course), batchPayload($payload))->assertSessionHasErrors($field);
    expect(Batch::count())->toBe(0);
})->with([
    'ends before start' => [['ends_on' => '2026-11-10'], 'ends_on'],
    'registration closes after end' => [['registration_closes_on' => '2026-12-01'], 'registration_closes_on'],
    'zero capacity' => [['capacity' => '0'], 'capacity'],
    'missing name' => [['name' => ''], 'name'],
]);

test('non-drip courses cannot have batches', function () {
    $course = Course::factory()->create(['drip_enabled' => false]);
    actingAsRole(Role::Admin);

    $this->post(route('admin.batches.store', $course), batchPayload())->assertSessionHasErrors('name');
});

test('the start date is frozen once the batch has started', function () {
    $batch = Batch::factory()->running()->create();
    actingAsRole(Role::Admin);

    $this->put(route('admin.batches.update', $batch), batchPayload(['starts_on' => '2026-11-20', 'ends_on' => '2026-11-30']))
        ->assertSessionHasErrors('starts_on');
});

test('capacity cannot go below the current number of students', function () {
    $batch = Batch::factory()->upcoming()->create(['capacity' => 5]);
    Enrollment::factory()->count(3)->create(['course_id' => $batch->course_id, 'batch_id' => $batch->id]);
    actingAsRole(Role::Admin);

    $this->put(route('admin.batches.update', $batch), batchPayload([
        'starts_on' => $batch->starts_on->toDateString(), 'ends_on' => $batch->ends_on->toDateString(), 'capacity' => '2',
    ]))->assertSessionHasErrors(['capacity' => 'Kuota tidak boleh kurang dari jumlah siswa saat ini (3).']);
});

test('a batch with students cannot be deleted; an empty one is soft deleted', function () {
    $full = Batch::factory()->upcoming()->create();
    Enrollment::factory()->create(['course_id' => $full->course_id, 'batch_id' => $full->id]);
    $empty = Batch::factory()->upcoming()->create();
    actingAsRole(Role::Admin);

    $this->delete(route('admin.batches.destroy', $full))->assertSessionHasErrors('batch');
    expect($full->refresh()->trashed())->toBeFalse();

    $this->delete(route('admin.batches.destroy', $empty))->assertRedirect();
    expect(Batch::withTrashed()->find($empty->id)->trashed())->toBeTrue();
});

test('the publish toggle hides a batch from new registrations', function () {
    $batch = Batch::factory()->upcoming()->create();
    actingAsRole(Role::Admin);

    $this->patch(route('admin.batches.publish', $batch))->assertRedirect();

    expect($batch->refresh()->is_published)->toBeFalse()
        ->and($batch->isOpenForRegistration())->toBeFalse();
});

test('staff and instructors cannot manage batches', function (Role $role) {
    $batch = Batch::factory()->upcoming()->create();
    $user = actingAsRole($role);
    $batch->course->instructors()->attach($user, ['role' => CourseInstructorRole::Lead->value]);

    $this->get(route('admin.batches.index', $batch->course))->assertForbidden();
    $this->post(route('admin.batches.store', $batch->course), batchPayload())->assertForbidden();
    $this->put(route('admin.batches.update', $batch), batchPayload())->assertForbidden();
    $this->patch(route('admin.batches.publish', $batch))->assertForbidden();
    $this->delete(route('admin.batches.destroy', $batch))->assertForbidden();
})->with([Role::Staff, Role::Instructor]);

test('publishing a drip course needs a published batch', function () {
    $course = publishableCourse(['drip_enabled' => true]);
    actingAsRole(Role::Admin);

    $this->patch(route('admin.courses.status', $course), ['status' => 'published'])
        ->assertSessionHasErrors(['status' => 'Kursus dengan jadwal bertahap (drip) harus memiliki minimal 1 batch yang dipublikasikan. Tambahkan batch di tab Batch.']);

    Batch::factory()->for($course)->unpublished()->create();
    $this->patch(route('admin.courses.status', $course), ['status' => 'published'])->assertSessionHasErrors('status');

    Batch::factory()->for($course)->upcoming()->create();
    $this->patch(route('admin.courses.status', $course), ['status' => 'published'])->assertSessionHasNoErrors();

    expect($course->refresh()->status->value)->toBe('published');
});
