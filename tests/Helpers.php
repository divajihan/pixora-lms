<?php

use App\Enums\CourseInstructorRole;
use App\Enums\LessonProgressStatus;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use App\Services\Payment\FakeGateway;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

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

/**
 * A drip course with a batch starting on $startsOn and lessons at offsets
 * 0, 1, 2 (+ a preview lesson at offset 5 and a draft at offset 0), plus an
 * active student enrollment in that batch.
 *
 * @return array{course: Course, batch: Batch, lessons: array<int, Lesson>, preview: Lesson, draft: Lesson, enrollment: Enrollment, student: User}
 */
function dripSetup(string $startsOn = '2026-11-15', array $courseAttributes = []): array
{
    $course = Course::factory()->published()->create(['drip_enabled' => true, ...$courseAttributes]);
    $module = Module::factory()->for($course)->create(['position' => 0]);
    $lessons = [];
    foreach ([0, 1, 2] as $offset) {
        $lessons[$offset] = Lesson::factory()->for($module)->published()->create([
            'course_id' => $course->id, 'drip_day_offset' => $offset, 'position' => $offset, 'title' => 'Lesson hari '.($offset + 1),
        ]);
    }
    $preview = Lesson::factory()->for($module)->published()->preview()->create(['course_id' => $course->id, 'drip_day_offset' => 5, 'position' => 3]);
    $draft = Lesson::factory()->for($module)->draft()->create(['course_id' => $course->id, 'drip_day_offset' => 0, 'position' => 4]);
    $batch = Batch::factory()->for($course)->startingOn($startsOn)->create();
    $student = User::factory()->create()->assignRole('student');
    $enrollment = Enrollment::factory()->create([
        'user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'enrolled_at' => now(),
    ]);

    return compact('course', 'batch', 'lessons', 'preview', 'draft', 'enrollment', 'student');
}

/**
 * A published non-drip course (certificates on) with two published lessons and
 * an active student enrollment.
 *
 * @return array{course: Course, lessons: array<int, Lesson>, enrollment: Enrollment, student: User}
 */
function certificateCourse(array $courseAttributes = []): array
{
    $course = Course::factory()->published()->create(['drip_enabled' => false, 'certificate_enabled' => true, 'title' => 'Desain Logo', ...$courseAttributes]);
    $module = Module::factory()->for($course)->create(['position' => 0]);
    $lessons = [];
    foreach ([0, 1] as $position) {
        $lessons[] = Lesson::factory()->for($module)->published()->create(['course_id' => $course->id, 'position' => $position]);
    }
    $course->instructors()->attach(instructorUser(), ['role' => CourseInstructorRole::Lead->value]);
    $student = User::factory()->create(['name' => 'Siti Aminah'])->assignRole('student');
    $enrollment = Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);

    return compact('course', 'lessons', 'enrollment', 'student');
}

/**
 * Marks the lessons completed for the enrollment.
 *
 * @param  iterable<Lesson>  $lessons
 */
function completeLessons(Enrollment $enrollment, iterable $lessons): void
{
    foreach ($lessons as $lesson) {
        LessonProgress::updateOrCreate(
            ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
            ['status' => LessonProgressStatus::Completed, 'completed_at' => now()],
        );
    }
}

function actingAsStudent(User $student): TestCase
{
    return test()->actingAs($student);
}
