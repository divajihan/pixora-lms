<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;

beforeEach(fn () => $this->student = User::factory()->create()->assignRole('student'));

test('a free course enrolls immediately without an order', function () {
    $course = Course::factory()->published()->free()->create(['access_duration_days' => 90]);

    $this->actingAs($this->student)->post(route('checkout.free', $course))
        ->assertRedirect(route('student.courses.learn', $course));

    $enrollment = Enrollment::sole();

    expect($enrollment->order_id)->toBeNull()
        ->and($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->expires_at->isSameDay(now()->addDays(90)))->toBeTrue()
        ->and(Order::count())->toBe(0);
});

test('taking a free course twice keeps one enrollment', function () {
    $course = Course::factory()->published()->free()->create();

    $this->actingAs($this->student)->post(route('checkout.free', $course));
    $this->actingAs($this->student)->post(route('checkout.free', $course));

    expect(Enrollment::count())->toBe(1);
});

test('a paid course cannot be taken for free', function () {
    $course = Course::factory()->published()->paid(150_000)->create();

    $this->actingAs($this->student)->post(route('checkout.free', $course))
        ->assertSessionHasErrors(['course' => 'Kursus ini berbayar. Silakan beli melalui halaman checkout.']);

    expect(Enrollment::count())->toBe(0);
});

test('a price of 0 counts as free even without the flag', function () {
    $course = Course::factory()->published()->create(['price' => 0, 'is_free' => false]);

    $this->actingAs($this->student)->post(route('checkout.free', $course))->assertSessionHasNoErrors();

    expect(Enrollment::count())->toBe(1);
});

test('unpublished free courses cannot be taken', function () {
    $course = Course::factory()->draft()->free()->create();

    $this->actingAs($this->student)->post(route('checkout.free', $course))->assertNotFound();

    expect(Enrollment::count())->toBe(0);
});

test('guests must log in before taking a free course', function () {
    $course = Course::factory()->published()->free()->create();

    $this->post(route('checkout.free', $course))->assertRedirect(route('login'));
});
