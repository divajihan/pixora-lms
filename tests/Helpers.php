<?php

use App\Enums\CourseInstructorRole;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

/**
 * Shared test helpers (loaded by tests/Pest.php).
 */
function actingAsRole(Role $role): User
{
    $user = User::factory()->create()->assignRole($role->value);
    test()->actingAs($user);

    return $user;
}

function instructorUser(): User
{
    return User::factory()->create()->assignRole(Role::Instructor->value);
}

/**
 * A course with one module and one published lesson, plus a lead instructor.
 */
function publishableCourse(array $attributes = []): Course
{
    $course = Course::factory()->draft()->create($attributes);
    $module = Module::factory()->for($course)->create();
    Lesson::factory()->for($module)->published()->create(['course_id' => $course->id]);
    $course->instructors()->attach(instructorUser(), ['role' => CourseInstructorRole::Lead->value]);

    return $course;
}
