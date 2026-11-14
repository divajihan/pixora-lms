<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the course page call-to-action matches the viewer', function () {
    [$student, $paid] = studentAndPaidCourse(199_000);
    $free = Course::factory()->published()->free()->create();

    // Guest
    $this->get(route('courses.show', $paid))->assertInertia(fn (Assert $page) => $page
        ->where('purchase.state', 'buy')
        ->where('purchase.price', 199_000)
        ->where('purchase.checkout_url', route('checkout.show', $paid)));
    $this->get(route('courses.show', $free))->assertInertia(fn (Assert $page) => $page->where('purchase.state', 'free'));

    // Student with a pending order
    $order = Order::factory()->forCourse($paid)->create(['user_id' => $student->id]);
    $this->actingAs($student)->get(route('courses.show', $paid))->assertInertia(fn (Assert $page) => $page
        ->where('purchase.state', 'pending_order')
        ->where('purchase.order_url', route('student.orders.show', $order)));

    // Student with access
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $paid->id]);
    $this->actingAs($student)->get(route('courses.show', $paid))->assertInertia(fn (Assert $page) => $page
        ->where('purchase.state', 'enrolled')
        ->where('purchase.learn_url', route('student.courses.learn', $paid)));

    // Staff/instructors cannot buy
    $this->actingAs(User::factory()->create()->assignRole('instructor'))
        ->get(route('courses.show', $free))
        ->assertInertia(fn (Assert $page) => $page->where('purchase.state', 'not_student'));
});

test('enrolled students are sent from checkout to the learning page', function () {
    [$student, $course] = studentAndPaidCourse();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);

    $this->actingAs($student)->get(route('checkout.show', $course))->assertRedirect(route('student.courses.learn', $course));
});
