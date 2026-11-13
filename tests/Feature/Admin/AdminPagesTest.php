<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => actingAsRole(Role::Admin));

test('the course list shows counts and supports search and filters', function () {
    $design = Category::factory()->create();
    $course = Course::factory()->draft()->create(['title' => 'Desain Logo', 'category_id' => $design->id]);
    $module = Module::factory()->for($course)->create();
    Lesson::factory()->count(3)->for($module)->create(['course_id' => $course->id]);
    Course::factory()->create(['title' => 'Laravel Dasar']);

    $this->get(route('admin.courses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/courses/index')->where('courses.total', 2));

    $this->get(route('admin.courses.index', ['q' => 'logo', 'status' => 'draft', 'kategori' => $design->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.total', 1)
            ->where('courses.data.0.title', 'Desain Logo')
            ->where('courses.data.0.modules_count', 1)
            ->where('courses.data.0.lessons_count', 3));
});

test('the course list does not run N+1 queries', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.courses.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    Course::factory()->count(2)->hasAttached(instructorUser(), ['role' => 'lead'], 'instructors')->create();
    $count(); // warm-up: the first request also fills the permission cache
    $few = $count();
    Course::factory()->count(8)->hasAttached(instructorUser(), ['role' => 'lead'], 'instructors')->create();

    expect($count())->toBe($few);
});

test('the edit page explains why a course cannot be published yet', function () {
    $course = Course::factory()->draft()->create();

    $this->get(route('admin.courses.edit', $course))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/courses/edit')
            ->has('publishBlockers', 2)
            ->where('statusTargets.0.value', 'published')
            ->has('availableInstructors'));
});

test('the curriculum page lists modules, lessons and totals', function () {
    $course = Course::factory()->draft()->create();
    $m1 = Module::factory()->for($course)->create(['position' => 0]);
    $m2 = Module::factory()->for($course)->create(['position' => 1]);
    Lesson::factory()->for($m1)->create(['course_id' => $course->id, 'duration_seconds' => 600]);
    Lesson::factory()->for($m2)->draft()->create(['course_id' => $course->id, 'duration_seconds' => 300]);
    Lesson::factory()->for($m2)->live()->create(['course_id' => $course->id, 'duration_seconds' => 3600]);

    $this->get(route('admin.courses.curriculum', $course))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/courses/curriculum')
            ->has('modules', 2)
            ->has('modules.1.lessons', 2)
            ->where('totals', ['modules' => 2, 'lessons' => 3, 'published_lessons' => 2, 'duration_seconds' => 4500])
            ->where('modules.0.lessons.0.video_url', 'https://www.youtube.com/watch?v=M7lc1UVf-VE')
            ->whereNot('modules.1.lessons.1.live', null));
});

test('the curriculum page loads in a constant number of queries', function () {
    $course = Course::factory()->draft()->create();
    $count = function () use ($course): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.courses.curriculum', $course))->assertOk();

        return count(DB::getQueryLog());
    };

    Lesson::factory()->for(Module::factory()->for($course))->create(['course_id' => $course->id]);
    $count(); // warm-up: the first request also fills the permission cache
    $few = $count();
    foreach (range(1, 3) as $i) {
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->count(3)->for($module)->create(['course_id' => $course->id]);
        Lesson::factory()->live()->for($module)->create(['course_id' => $course->id]);
    }

    expect($count())->toBe($few);
});

test('the create page renders', function () {
    $this->get(route('admin.courses.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/courses/create')->has('categories'));
});

test('user search and role filter', function () {
    User::factory()->create(['name' => 'Rina Kartika', 'email' => 'rina@pixora.test'])->assignRole('instructor');
    User::factory()->create(['name' => 'Budi', 'email' => 'budi@pixora.test'])->assignRole('student');

    $this->get(route('admin.users.index', ['q' => 'rina']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index')->where('users.total', 1)->where('users.data.0.email', 'rina@pixora.test'));

    $this->get(route('admin.users.index', ['peran' => 'student']))
        ->assertInertia(fn (Assert $page) => $page->where('users.total', 1)->where('users.data.0.name', 'Budi'));
});

test('shared props expose permissions for navigation', function () {
    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('courses.manage')));
});
