<?php

use App\Enums\CourseInstructorRole;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use App\Services\Payment\FakeGateway;
use Illuminate\Testing\TestResponse;

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

/**
 * Sends a signed (FakeGateway, Midtrans-format) notification to the webhook.
 *
 * @param  array<string, mixed>  $overrides
 */
function sendWebhook(Order $order, string $status = 'settlement', array $overrides = []): TestResponse
{
    $payload = app(FakeGateway::class)->notification($order, $status, $overrides);

    return test()->postJson(route('webhooks.midtrans'), $payload);
}

/**
 * A verified student and a published paid course.
 *
 * @return array{0: User, 1: Course}
 */
function studentAndPaidCourse(int $price = 199_000, array $courseAttributes = []): array
{
    $student = User::factory()->create()->assignRole('student');
    $course = Course::factory()->published()->paid($price)->create($courseAttributes);

    return [$student, $course];
}
