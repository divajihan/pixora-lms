<?php

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Services\Payment\MidtransGateway;
use App\Services\Payment\PaymentGateway;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => config(['pixora.payments.gateway' => 'fake']));

test('the order total always comes from the server, never from the request', function () {
    [$student, $course] = studentAndPaidCourse(249_000);

    $this->actingAs($student)
        ->post(route('checkout.store', $course), ['price' => 1, 'total' => 1, 'subtotal' => 1])
        ->assertRedirect();

    $order = Order::with('items')->sole();

    expect($order->total)->toBe(249_000)
        ->and($order->subtotal)->toBe(249_000)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->items->sole()->price)->toBe(249_000)
        ->and($order->items->sole()->title_snapshot)->toBe($course->title)
        ->and($order->order_number)->toMatch('/^PXR-\d{8}-[2-9A-HJ-NP-Z]{6}$/')
        ->and($order->payment_token)->toBe('fake-'.$order->order_number)
        ->and($order->expires_at->diffInHours(now()->addDay(), true))->toBeLessThan(1);
});

test('checkout redirects to the order page which offers payment', function () {
    [$student, $course] = studentAndPaidCourse();

    $response = $this->actingAs($student)->post(route('checkout.store', $course));
    $order = Order::sole();

    $response->assertRedirect(route('student.orders.show', $order));

    $this->get(route('student.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/orders/show')
            ->where('order.total', $course->price)
            ->where('payment.driver', 'fake')
            ->where('payment.token', 'fake-'.$order->order_number));
});

test('a pending order for the same course is reused', function () {
    [$student, $course] = studentAndPaidCourse();

    $this->actingAs($student)->post(route('checkout.store', $course));
    $this->actingAs($student)->post(route('checkout.store', $course));

    expect(Order::count())->toBe(1);
});

test('an expired pending order is not reused', function () {
    [$student, $course] = studentAndPaidCourse();
    Order::factory()->forCourse($course)->expiredPending()->create(['user_id' => $student->id]);

    $this->actingAs($student)->post(route('checkout.store', $course));

    expect(Order::count())->toBe(2)
        ->and(Order::latest('id')->first()->isPayable())->toBeTrue();
});

test('old orders keep their price snapshot after the course price changes', function () {
    [$student, $course] = studentAndPaidCourse(150_000);
    $this->actingAs($student)->post(route('checkout.store', $course));

    $course->update(['price' => 300_000, 'title' => 'Judul Baru']);

    $item = Order::sole()->items->sole();
    expect($item->price)->toBe(150_000)->and($item->title_snapshot)->not->toBe('Judul Baru');
});

test('draft, archived, scheduled and free courses cannot be bought', function (Closure $makeCourse) {
    $student = User::factory()->create()->assignRole('student');
    $course = $makeCourse();

    // Unpublished courses are 404 (CoursePolicy); free ones redirect back with an error.
    expect($this->actingAs($student)->post(route('checkout.store', $course))->status())->toBeIn([302, 404]);

    expect(Order::count())->toBe(0);
})->with([
    'draft' => fn () => Course::factory()->draft()->paid()->create(),
    'archived' => fn () => Course::factory()->archived()->paid()->create(),
    'scheduled' => fn () => Course::factory()->scheduled()->paid()->create(),
    'free' => fn () => Course::factory()->published()->free()->create(),
]);

test('a free course points to the free enrollment instead of checkout', function () {
    $student = User::factory()->create()->assignRole('student');
    $course = Course::factory()->published()->free()->create();

    $this->actingAs($student)->post(route('checkout.store', $course))
        ->assertSessionHasErrors(['course' => 'Kursus ini gratis. Gunakan tombol "Ambil gratis".']);
});

test('students who already have access cannot buy again', function () {
    [$student, $course] = studentAndPaidCourse();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);

    $this->actingAs($student)->post(route('checkout.store', $course))
        ->assertSessionHasErrors(['course' => 'Anda sudah terdaftar di kursus ini.']);

    expect(Order::count())->toBe(0);
});

test('students whose access expired or was revoked can buy again', function (string $state) {
    [$student, $course] = studentAndPaidCourse();
    Enrollment::factory()->{$state}()->create(['user_id' => $student->id, 'course_id' => $course->id]);

    $this->actingAs($student)->post(route('checkout.store', $course))->assertSessionHasNoErrors();

    expect(Order::count())->toBe(1);
})->with(['expired', 'revoked']);

test('guests are sent to login and then back to checkout', function () {
    [, $course] = studentAndPaidCourse();

    $this->get(route('checkout.show', $course))->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('checkout.show', $course));
});

test('only verified students can check out', function () {
    [, $course] = studentAndPaidCourse();

    $this->actingAs(User::factory()->unverified()->create()->assignRole('student'))
        ->post(route('checkout.store', $course))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->create()->assignRole('instructor'))
        ->post(route('checkout.store', $course))
        ->assertForbidden();
});

test('the checkout endpoint is rate limited', function () {
    [$student, $course] = studentAndPaidCourse();
    RateLimiter::clear('');

    foreach (range(1, 10) as $i) {
        $this->actingAs($student)->post(route('checkout.store', $course))->assertRedirect();
    }

    $this->actingAs($student)->post(route('checkout.store', $course))->assertTooManyRequests();
});

test('the checkout summary page shows the server price', function () {
    [$student, $course] = studentAndPaidCourse(175_000);

    $this->actingAs($student)->get(route('checkout.show', $course))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('checkout/show')->where('course.price', 175_000));
});

test('gateway errors are shown as a validation error and the order stays pending', function () {
    config(['pixora.payments.gateway' => 'midtrans', 'services.midtrans.server_key' => '']);
    app()->forgetInstance(PaymentGateway::class);
    app()->forgetInstance(MidtransGateway::class);
    [$student, $course] = studentAndPaidCourse();

    $this->actingAs($student)->post(route('checkout.store', $course))->assertSessionHasErrors('payment');

    expect(Order::sole()->payment_token)->toBeNull();
});
