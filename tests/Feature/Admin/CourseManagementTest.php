<?php

use App\Enums\CourseInstructorRole;
use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = actingAsRole(Role::Admin);
});

function coursePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Kursus Baru',
        'slug' => '',
        'summary' => 'Ringkasan singkat.',
        'description' => 'Deskripsi lengkap.',
        'category_id' => Category::factory()->create()->id,
        'is_free' => false,
        'price' => 150000,
        'drip_enabled' => true,
        'access_duration_days' => 365,
        'certificate_enabled' => true,
    ], $overrides);
}

test('admin creates a draft course with an auto slug and a random-named thumbnail', function () {
    $this->post(route('admin.courses.store'), coursePayload([
        'thumbnail' => UploadedFile::fake()->image('sampul saya.png', 1280, 720)->size(300),
    ]))->assertRedirect();

    $course = Course::where('title', 'Kursus Baru')->firstOrFail();

    expect($course->status)->toBe(CourseStatus::Draft)
        ->and($course->slug)->toBe('kursus-baru')
        ->and($course->price)->toBe(150000)
        ->and($course->created_by)->toBe($this->admin->id)
        ->and($course->thumbnail_path)->toStartWith('course-thumbnails/')
        ->and($course->thumbnail_path)->not->toContain('sampul');

    Storage::disk('public')->assertExists($course->thumbnail_path);
});

test('auto slugs are unique, including against soft-deleted courses', function () {
    Course::factory()->create(['slug' => 'kursus-baru'])->delete();

    $this->post(route('admin.courses.store'), coursePayload())->assertRedirect();

    expect(Course::where('title', 'Kursus Baru')->value('slug'))->toBe('kursus-baru-2');
});

test('a free course always costs 0', function () {
    $this->post(route('admin.courses.store'), coursePayload(['is_free' => true, 'price' => 999000]))->assertRedirect();

    expect(Course::where('title', 'Kursus Baru')->firstOrFail())
        ->is_free->toBeTrue()
        ->price->toBe(0);
});

test('course validation rules are enforced', function (Closure|array $overrides, string $field) {
    $overrides = $overrides instanceof Closure ? $overrides() : $overrides;

    $this->post(route('admin.courses.store'), coursePayload($overrides))->assertSessionHasErrors($field);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'paid without price' => [['is_free' => false, 'price' => null], 'price'],
    'price below minimum' => [['price' => 500], 'price'],
    'invalid slug' => [['slug' => 'Bukan Slug!'], 'slug'],
    'unknown category' => [['category_id' => 999999], 'category_id'],
    // Uploads are built lazily (inside the test), not when the dataset is loaded.
    'non-image thumbnail' => [fn () => ['thumbnail' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')], 'thumbnail'],
    'thumbnail too large' => [fn () => ['thumbnail' => UploadedFile::fake()->image('besar.png')->size(3000)], 'thumbnail'],
    'access days zero' => [['access_duration_days' => 0], 'access_duration_days'],
]);

test('a manually chosen slug must be unique', function () {
    Course::factory()->create(['slug' => 'sudah-ada']);

    $this->post(route('admin.courses.store'), coursePayload(['slug' => 'sudah-ada']))
        ->assertSessionHasErrors(['slug' => 'Slug sudah digunakan.']);
});

test('updating replaces the thumbnail and deletes the old file', function () {
    $course = Course::factory()->draft()->create(['thumbnail_path' => 'course-thumbnails/lama.jpg']);
    Storage::disk('public')->put('course-thumbnails/lama.jpg', 'x');

    $this->put(route('admin.courses.update', $course), coursePayload([
        'title' => 'Judul Diubah',
        'slug' => $course->slug,
        'thumbnail' => UploadedFile::fake()->image('baru.png'),
    ]))->assertRedirect(route('admin.courses.edit', $course));

    $course->refresh();
    expect($course->title)->toBe('Judul Diubah')->and($course->thumbnail_path)->not->toBe('course-thumbnails/lama.jpg');
    Storage::disk('public')->assertMissing('course-thumbnails/lama.jpg');
    Storage::disk('public')->assertExists($course->thumbnail_path);
});

test('the thumbnail can be removed', function () {
    $course = Course::factory()->draft()->create(['thumbnail_path' => 'course-thumbnails/lama.jpg']);
    Storage::disk('public')->put('course-thumbnails/lama.jpg', 'x');

    $this->put(route('admin.courses.update', $course), coursePayload(['slug' => $course->slug, 'remove_thumbnail' => true]));

    expect($course->refresh()->thumbnail_path)->toBeNull();
    Storage::disk('public')->assertMissing('course-thumbnails/lama.jpg');
});

test('deleting a course is a soft delete', function () {
    $course = Course::factory()->create();

    $this->delete(route('admin.courses.destroy', $course))->assertRedirect(route('admin.courses.index'));

    expect(Course::find($course->id))->toBeNull()
        ->and(Course::withTrashed()->find($course->id)?->trashed())->toBeTrue();
});

test('publishing requires at least one published lesson', function () {
    $course = Course::factory()->draft()->create();
    $course->instructors()->attach(instructorUser(), ['role' => 'lead']);
    $module = Module::factory()->for($course)->create();
    Lesson::factory()->for($module)->draft()->create(['course_id' => $course->id]);

    $this->patch(route('admin.courses.status', $course), ['status' => 'published'])
        ->assertSessionHasErrors(['status' => 'Kursus belum memiliki lesson berstatus terbit. Tambahkan dan terbitkan minimal 1 lesson di halaman kurikulum.']);

    expect($course->refresh()->status)->toBe(CourseStatus::Draft);
});

test('lessons in a deleted module do not count for publishing', function () {
    $course = publishableCourse();
    $course->modules()->first()->delete();

    $this->patch(route('admin.courses.status', $course), ['status' => 'published'])->assertSessionHasErrors('status');
});

test('publishing requires a lead instructor', function () {
    $course = publishableCourse();
    $course->instructors()->updateExistingPivot($course->instructors()->first()->id, ['role' => 'assistant']);

    $this->patch(route('admin.courses.status', $course), ['status' => 'published'])
        ->assertSessionHasErrors('status');

    expect($course->refresh()->status)->toBe(CourseStatus::Draft);
});

test('a course meeting the rules is published, archived and restored', function () {
    $course = publishableCourse();

    $this->patch(route('admin.courses.status', $course), ['status' => 'published'])->assertSessionHasNoErrors();
    $course->refresh();
    expect($course->status)->toBe(CourseStatus::Published)->and($course->published_at)->not->toBeNull();
    $publishedAt = $course->published_at;

    $this->patch(route('admin.courses.status', $course), ['status' => 'archived'])->assertSessionHasNoErrors();
    expect($course->refresh()->status)->toBe(CourseStatus::Archived);

    $this->patch(route('admin.courses.status', $course), ['status' => 'published'])->assertSessionHasNoErrors();
    expect($course->refresh()->published_at->equalTo($publishedAt))->toBeTrue();
});

test('draft courses cannot jump straight to archived', function () {
    $course = publishableCourse();

    $this->patch(route('admin.courses.status', $course), ['status' => 'archived'])->assertSessionHasErrors('status');
});

test('only users with the instructor role can be assigned', function () {
    $course = Course::factory()->draft()->create();
    $student = DB::transaction(fn () => User::factory()->create()->assignRole('student'));

    $this->post(route('admin.courses.instructors.store', $course), ['user_id' => $student->id, 'role' => 'lead'])
        ->assertSessionHasErrors(['user_id' => 'Hanya pengguna dengan peran instruktur yang dapat ditugaskan.']);

    $instructor = instructorUser();
    $this->post(route('admin.courses.instructors.store', $course), ['user_id' => $instructor->id, 'role' => 'assistant'])
        ->assertSessionHasNoErrors();

    expect($course->instructors()->first()->assignment->role)->toBe(CourseInstructorRole::Assistant);
});

test('a published course keeps at least one lead instructor', function () {
    $course = publishableCourse();
    $this->patch(route('admin.courses.status', $course), ['status' => 'published']);
    $lead = $course->instructors()->first();

    $this->delete(route('admin.courses.instructors.destroy', [$course, $lead]))->assertSessionHasErrors('user_id');
    $this->post(route('admin.courses.instructors.store', $course), ['user_id' => $lead->id, 'role' => 'assistant'])->assertSessionHasErrors('user_id');

    $other = instructorUser();
    $this->post(route('admin.courses.instructors.store', $course), ['user_id' => $other->id, 'role' => 'lead']);
    $this->delete(route('admin.courses.instructors.destroy', [$course, $lead]))->assertSessionHasNoErrors();

    expect($course->instructors()->pluck('users.id')->all())->toBe([$other->id]);
});
