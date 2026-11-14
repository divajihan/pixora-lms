<?php

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Policies\EnrollmentPolicy;
use App\Policies\OrderPolicy;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    [$this->owner, $this->course] = studentAndPaidCourse();
    $this->order = Order::factory()->forCourse($this->course)->create(['user_id' => $this->owner->id]);
});

test('students only see their own orders', function () {
    Order::factory()->forCourse($this->course)->create(); // someone else's

    $this->actingAs($this->owner)->get(route('student.orders.index'))
        ->assertInertia(fn (Assert $page) => $page->component('student/orders/index')->where('orders.total', 1));

    $this->actingAs($this->owner)->get(route('student.orders.show', $this->order))->assertOk();
    $this->actingAs($this->owner)->get(route('student.orders.invoice', $this->order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('student/orders/invoice'));

    $other = User::factory()->create()->assignRole('student');
    $this->actingAs($other)->get(route('student.orders.show', $this->order))->assertNotFound();
    $this->actingAs($other)->get(route('student.orders.invoice', $this->order))->assertNotFound();
});

test('the Snap token is never exposed to other users', function () {
    $this->order->forceFill(['payment_token' => 'SECRET-SNAP-TOKEN'])->save();
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)->get(route('admin.orders.show', $this->order))->assertDontSee('SECRET-SNAP-TOKEN');
    $this->actingAs($this->owner)->get(route('student.orders.show', $this->order))
        ->assertInertia(fn (Assert $page) => $page->where('payment.token', 'SECRET-SNAP-TOKEN'));
});

test('the admin order area is limited to orders.manage (admin, staff)', function (?Role $role, int $status) {
    if ($role) {
        actingAsRole($role);
    }

    $index = $this->get(route('admin.orders.index'));
    $show = $this->get(route('admin.orders.show', $this->order));

    if ($status === 302) {
        $index->assertRedirect(route('login'));

        return;
    }

    expect($index->status())->toBe($status)->and($show->status())->toBe($status);
})->with([
    'admin' => [Role::Admin, 200],
    'staff' => [Role::Staff, 200],
    'instructor' => [Role::Instructor, 403],
    'student' => [Role::Student, 403],
    'guest' => [null, 302],
]);

test('only admins can mark orders paid or refund them', function (Role $role, bool $allowed) {
    $user = actingAsRole($role);
    $paid = Order::factory()->forCourse(Course::factory()->paid()->create())->paid()->create();

    $markPaid = $this->post(route('admin.orders.mark-paid', $this->order), ['note' => 'Transfer manual BCA']);
    $refund = $this->post(route('admin.orders.refund', $paid), ['note' => 'Permintaan siswa']);

    if ($allowed) {
        $markPaid->assertSessionHasNoErrors();
        $refund->assertSessionHasNoErrors();
    } else {
        $markPaid->assertForbidden();
        $refund->assertForbidden();
    }

    expect((new OrderPolicy)->markPaid($user, $this->order))->toBe($allowed)
        ->and((new OrderPolicy)->refund($user, $paid))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, true],
    'staff' => [Role::Staff, false],
]);

test('admin order list supports search and status filter', function () {
    actingAsRole(Role::Admin);
    Order::factory()->forCourse($this->course)->paid()->create();

    $this->get(route('admin.orders.index', ['q' => $this->order->order_number]))
        ->assertInertia(fn (Assert $page) => $page->component('admin/orders/index')->where('orders.total', 1));

    $this->get(route('admin.orders.index', ['q' => $this->owner->email]))
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 1)->where('orders.data.0.order_number', $this->order->order_number));

    $this->get(route('admin.orders.index', ['status' => 'paid']))
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 1)->where('orders.data.0.status', 'paid'));

    $this->get(route('admin.orders.show', $this->order))
        ->assertInertia(fn (Assert $page) => $page->component('admin/orders/show')->where('can.markPaid', true)->where('can.refund', false));
});

test('the learning page is only for the owner of an accessible enrollment', function (string $state, int $status) {
    Enrollment::factory()->{$state}()->create(['user_id' => $this->owner->id, 'course_id' => $this->course->id]);

    $this->actingAs($this->owner)->get(route('learn.course', $this->course))->assertStatus($status);
})->with([
    'active' => ['active', 200],
    'lapsed (past expires_at)' => ['lapsed', 403],
    'expired' => ['expired', 403],
    'revoked' => ['revoked', 403],
]);

test('students without an enrollment, other roles and guests cannot open the learning page', function () {
    $this->actingAs($this->owner)->get(route('learn.course', $this->course))->assertNotFound();

    // The player is role-agnostic: without an enrollment it is simply not found.
    $this->actingAs(User::factory()->create()->assignRole('instructor'))
        ->get(route('learn.course', $this->course))->assertNotFound();

    auth()->logout();
    $this->get(route('learn.course', $this->course))->assertRedirect(route('login'));
});

test('the enrollment policy only allows the owner', function () {
    $enrollment = Enrollment::factory()->create(['user_id' => $this->owner->id, 'course_id' => $this->course->id]);
    $other = User::factory()->create();

    expect(app(EnrollmentPolicy::class)->learn($this->owner, $enrollment)->allowed())->toBeTrue()
        ->and(app(EnrollmentPolicy::class)->learn($other, $enrollment)->status())->toBe(404);
});

test('my courses lists accessible and ended enrollments', function () {
    Enrollment::factory()->create(['user_id' => $this->owner->id, 'course_id' => $this->course->id]);
    Enrollment::factory()->expired()->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)->get(route('student.courses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/courses/index')
            ->has('enrollments', 2)
            ->where('enrollments', fn ($items) => collect($items)->where('is_accessible', true)->count() === 1));
});
