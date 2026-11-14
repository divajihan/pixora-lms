<?php

use App\Enums\Role;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Policies\CoursePolicy;
use App\Policies\LessonPolicy;
use App\Policies\ModulePolicy;
use Illuminate\Testing\TestResponse;

/**
 * Every admin content/user route, as [method, route name, params resolver, payload].
 *
 * @return array<string, array{0: string, 1: string, 2: Closure(Course, Module, Lesson, User): array<int, mixed>}>
 */
function adminRoutes(): array
{
    $none = fn () => [];
    $course = fn (Course $course) => [$course];

    return [
        'courses.index' => ['get', 'admin.courses.index', $none],
        'courses.create' => ['get', 'admin.courses.create', $none],
        'courses.store' => ['post', 'admin.courses.store', $none],
        'courses.edit' => ['get', 'admin.courses.edit', $course],
        'courses.update' => ['put', 'admin.courses.update', $course],
        'courses.destroy' => ['delete', 'admin.courses.destroy', $course],
        'courses.status' => ['patch', 'admin.courses.status', $course],
        'courses.instructors.store' => ['post', 'admin.courses.instructors.store', $course],
        'courses.instructors.destroy' => ['delete', 'admin.courses.instructors.destroy', fn (Course $c, Module $m, Lesson $l, User $u) => [$c, $u]],
        'courses.curriculum' => ['get', 'admin.courses.curriculum', $course],
        'courses.curriculum.reorder' => ['put', 'admin.courses.curriculum.reorder', $course],
        'modules.store' => ['post', 'admin.modules.store', $course],
        'modules.update' => ['put', 'admin.modules.update', fn (Course $c, Module $m) => [$m]],
        'modules.destroy' => ['delete', 'admin.modules.destroy', fn (Course $c, Module $m) => [$m]],
        'lessons.store' => ['post', 'admin.lessons.store', fn (Course $c, Module $m) => [$m]],
        'lessons.update' => ['put', 'admin.lessons.update', fn (Course $c, Module $m, Lesson $l) => [$l]],
        'lessons.destroy' => ['delete', 'admin.lessons.destroy', fn (Course $c, Module $m, Lesson $l) => [$l]],
        'users.index' => ['get', 'admin.users.index', $none],
        'users.store' => ['post', 'admin.users.store', $none],
        'users.update' => ['put', 'admin.users.update', fn (Course $c, Module $m, Lesson $l, User $u) => [$u]],
    ];
}

function callAdminRoute(string $routeKey): TestResponse
{
    [$method, $name, $params] = adminRoutes()[$routeKey];

    $course = Course::factory()->draft()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create(['course_id' => $course->id]);
    $other = instructorUser();
    $course->instructors()->attach($other, ['role' => 'lead']);

    return test()->{$method}(route($name, $params($course, $module, $lesson, $other)), []);
}

test('staff, instructors and students are forbidden from every admin content and user route', function (Role $role, string $routeKey) {
    actingAsRole($role);

    callAdminRoute($routeKey)->assertForbidden();
})->with([Role::Staff, Role::Instructor, Role::Student])->with(array_keys(adminRoutes()));

test('guests are sent to login from every admin route', function (string $routeKey) {
    callAdminRoute($routeKey)->assertRedirect(route('login'));
})->with(array_keys(adminRoutes()));

test('admins pass authorization on every admin route', function (string $routeKey) {
    actingAsRole(Role::Admin);

    $status = callAdminRoute($routeKey)->status();

    // 200 (pages) or 302 (redirect back, possibly with validation errors) — never 403/404.
    expect($status)->toBeIn([200, 302]);
})->with(array_keys(adminRoutes()));

test('staff can still open the shared admin dashboard', function () {
    actingAsRole(Role::Staff);

    $this->get(route('admin.dashboard'))->assertOk();
});

test('instructors only see courses assigned to them (read-only)', function () {
    $instructor = actingAsRole(Role::Instructor);
    $mine = Course::factory()->draft()->create(['title' => 'Kursus Saya']);
    $mine->instructors()->attach($instructor, ['role' => 'assistant']);
    $notMine = Course::factory()->create(['title' => 'Kursus Orang Lain']);

    $this->get(route('instructor.courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('instructor/courses/index')
            ->has('courses', 1)
            ->where('courses.0.title', 'Kursus Saya'));

    $this->get(route('instructor.courses.show', $mine))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('instructor/courses/show'));

    $this->get(route('instructor.courses.show', $notMine))->assertNotFound();
});

test('the instructor area is closed to other roles and guests', function (?Role $role) {
    $course = Course::factory()->create();

    if ($role) {
        actingAsRole($role);
        $this->get(route('instructor.courses.index'))->assertForbidden();
        $this->get(route('instructor.courses.show', $course))->assertForbidden();
    } else {
        $this->get(route('instructor.courses.index'))->assertRedirect(route('login'));
    }
})->with([Role::Admin, Role::Staff, Role::Student, null]);

test('course, module and lesson policies per role', function (Role $role, bool $manage, bool $viewAssignedCurriculum, bool $viewOtherCurriculum) {
    $user = User::factory()->create()->assignRole($role->value);
    $assigned = Course::factory()->create();
    $assigned->instructors()->attach($user, ['role' => 'lead']);
    $other = Course::factory()->create();
    $module = Module::factory()->for($other)->create();
    $lesson = Lesson::factory()->for($module)->create(['course_id' => $other->id]);

    $courses = new CoursePolicy;

    expect($courses->manage($user))->toBe($manage)
        ->and($courses->update($user, $other))->toBe($manage)
        ->and($courses->delete($user, $other))->toBe($manage)
        ->and($courses->viewCurriculum($user, $assigned)->allowed())->toBe($viewAssignedCurriculum)
        ->and($courses->viewCurriculum($user, $other)->allowed())->toBe($viewOtherCurriculum)
        ->and((new ModulePolicy)->update($user, $module))->toBe($manage)
        ->and((new ModulePolicy)->delete($user, $module))->toBe($manage)
        ->and(app(LessonPolicy::class)->update($user, $lesson))->toBe($manage)
        ->and(app(LessonPolicy::class)->delete($user, $lesson))->toBe($manage);
})->with([
    'admin' => [Role::Admin, true, true, true],
    'staff' => [Role::Staff, false, false, false],
    'instructor' => [Role::Instructor, false, true, false],
    'student' => [Role::Student, false, false, false],
]);

test('guests may only view published courses through the policy', function () {
    $policy = new CoursePolicy;

    expect($policy->view(null, Course::factory()->create())->allowed())->toBeTrue()
        ->and($policy->view(null, Course::factory()->draft()->create())->status())->toBe(404);
});
