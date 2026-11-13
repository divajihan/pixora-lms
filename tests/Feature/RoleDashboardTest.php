<?php

use App\Enums\Role;
use App\Models\User;

function userWithRole(Role $role): User
{
    return User::factory()->create()->assignRole($role->value);
}

test('guests are redirected to login from every dashboard', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['dashboard', 'admin.dashboard', 'instructor.dashboard', 'student.dashboard']);

test('after login users are sent to their role dashboard', function (Role $role, string $expected) {
    $this->actingAs(userWithRole($role))
        ->get(route('dashboard'))
        ->assertRedirect(route($expected));
})->with([
    'admin' => [Role::Admin, 'admin.dashboard'],
    'staff follows admin' => [Role::Staff, 'admin.dashboard'],
    'instructor' => [Role::Instructor, 'instructor.dashboard'],
    'student' => [Role::Student, 'student.dashboard'],
]);

test('login redirects through the dashboard entry point', function () {
    $user = userWithRole(Role::Instructor);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('each role can open its own dashboard', function (Role $role, string $route, string $component) {
    $this->actingAs(userWithRole($role))
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    [Role::Admin, 'admin.dashboard', 'admin/dashboard'],
    [Role::Staff, 'admin.dashboard', 'admin/dashboard'],
    [Role::Instructor, 'instructor.dashboard', 'instructor/dashboard'],
    [Role::Student, 'student.dashboard', 'student/dashboard'],
]);

test('role middleware rejects the wrong role with 403', function (Role $role, string $route) {
    $this->actingAs(userWithRole($role))->get(route($route))->assertForbidden();
})->with([
    'student → admin' => [Role::Student, 'admin.dashboard'],
    'student → instructor' => [Role::Student, 'instructor.dashboard'],
    'instructor → admin' => [Role::Instructor, 'admin.dashboard'],
    'staff → instructor' => [Role::Staff, 'instructor.dashboard'],
    'admin → student' => [Role::Admin, 'student.dashboard'],
]);

test('users without a role cannot reach any dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertForbidden();
});

test('unverified users must verify their email before reaching dashboards', function (string $route) {
    $user = User::factory()->unverified()->create()->assignRole(Role::Student->value);

    $this->actingAs($user)->get(route($route))->assertRedirect(route('verification.notice'));
})->with(['dashboard', 'student.dashboard']);
