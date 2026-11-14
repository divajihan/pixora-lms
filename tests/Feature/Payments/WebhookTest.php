<?php

use App\Actions\Payments\ApplyPaymentNotification;
use App\Actions\Payments\ReconcileOrder;
use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\PaymentSucceeded;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\MidtransPayload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['pixora.payments.gateway' => 'fake']);
    Notification::fake();
    [$this->student, $this->course] = studentAndPaidCourse(199_000, ['access_duration_days' => null]);
    $this->order = Order::factory()->forCourse($this->course)->create(['user_id' => $this->student->id]);
});

test('a valid settlement marks the order paid and enrolls the student once', function () {
    sendWebhook($this->order, 'settlement')->assertOk()->assertJson(['message' => 'processed']);

    $order = $this->order->refresh();
    $enrollment = Enrollment::sole();
    $payment = Payment::sole();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($enrollment->user_id)->toBe($this->student->id)
        ->and($enrollment->order_id)->toBe($order->id)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->expires_at)->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->gross_amount)->toBe(199_000)
        ->and($payment->raw_payload['transaction_status'])->toBe('settlement')
        ->and(WebhookEvent::sole()->processed_at)->not->toBeNull();

    Notification::assertSentToTimes($this->student, PaymentSucceeded::class, 1);
});

test('the same webhook sent twice is processed once', function () {
    sendWebhook($this->order)->assertOk()->assertJson(['message' => 'processed']);
    sendWebhook($this->order)->assertOk()->assertJson(['message' => 'duplicate']);

    expect(Enrollment::count())->toBe(1)
        ->and(WebhookEvent::count())->toBe(1)
        ->and(Payment::count())->toBe(1);

    Notification::assertSentToTimes($this->student, PaymentSucceeded::class, 1);
});

test('concurrent deliveries still create exactly one enrollment', function () {
    // 1) Another worker already recorded this exact event (it holds the unique key).
    $payload = app(FakeGateway::class)->notification($this->order);
    WebhookEvent::create(['gateway' => 'fake', 'event_id' => MidtransPayload::parse($payload)->eventId, 'payload' => $payload]);
    sendWebhook($this->order)->assertOk()->assertJson(['message' => 'duplicate']);
    expect(Enrollment::count())->toBe(0);

    // 2) A different success event (card capture) races with a settlement, and a
    //    concurrent process has already created the enrollment row.
    Enrollment::factory()->create(['user_id' => $this->student->id, 'course_id' => $this->course->id, 'order_id' => $this->order->id]);
    sendWebhook($this->order, 'capture', ['transaction_id' => 'card-1', 'fraud_status' => 'accept'])->assertOk();
    sendWebhook($this->order, 'settlement', ['transaction_id' => 'va-1'])->assertOk();

    expect(Enrollment::count())->toBe(1)
        ->and($this->order->refresh()->status)->toBe(OrderStatus::Paid);
});

test('an invalid signature is rejected with 403 and changes nothing', function () {
    sendWebhook($this->order, 'settlement', ['signature_key' => str_repeat('a', 128)])->assertForbidden();

    // Tampering with the amount breaks the signature too.
    $payload = app(FakeGateway::class)->notification($this->order);
    $payload['gross_amount'] = '1.00';
    $this->postJson(route('webhooks.midtrans'), $payload)->assertForbidden();

    $this->postJson(route('webhooks.midtrans'), ['order_id' => $this->order->order_number])->assertForbidden();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(Enrollment::count())->toBe(0)
        ->and(WebhookEvent::count())->toBe(0);
});

test('a signed notification with a different amount is rejected and logged', function () {
    Log::spy();

    sendWebhook($this->order, 'settlement', ['gross_amount' => '1000.00'])->assertStatus(422);

    expect($this->order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(Enrollment::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(WebhookEvent::count())->toBe(0);

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'Payment amount mismatch')->once();
});

test('unsuccessful outcomes do not create enrollments', function (string $status, OrderStatus $expected, array $overrides = []) {
    sendWebhook($this->order, $status, $overrides)->assertOk();

    expect($this->order->refresh()->status)->toBe($expected)
        ->and(Enrollment::count())->toBe(0);

    Notification::assertNothingSent();
})->with([
    'pending' => ['pending', OrderStatus::Pending],
    'deny' => ['deny', OrderStatus::Failed],
    'cancel' => ['cancel', OrderStatus::Failed],
    'failure' => ['failure', OrderStatus::Failed],
    'expire' => ['expire', OrderStatus::Expired],
    // Midtrans sends card challenges as capture + fraud_status=challenge with status_code 201.
    'card challenge' => ['capture', OrderStatus::Pending, ['fraud_status' => 'challenge', 'status_code' => '201']],
]);

test('card challenge waits, then accept enrolls', function () {
    sendWebhook($this->order, 'capture', ['fraud_status' => 'challenge', 'status_code' => '201'])->assertOk();
    expect(Enrollment::count())->toBe(0);

    sendWebhook($this->order, 'capture', ['fraud_status' => 'accept', 'status_code' => '200'])->assertOk();
    expect(Enrollment::count())->toBe(1)->and($this->order->refresh()->status)->toBe(OrderStatus::Paid);
});

test('a paid order is never downgraded by late notifications', function (string $status) {
    sendWebhook($this->order, 'settlement')->assertOk();
    sendWebhook($this->order, $status, ['transaction_id' => 'late-'.$status])->assertOk();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Enrollment::sole()->status)->toBe(EnrollmentStatus::Active);
})->with(['expire', 'deny', 'cancel', 'pending', 'refund']);

test('a late settlement still pays an order that expired locally', function () {
    $this->order->forceFill(['status' => OrderStatus::Expired])->save();

    sendWebhook($this->order, 'settlement')->assertOk();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid)->and(Enrollment::count())->toBe(1);
});

test('enrollment expiry follows the course access duration', function () {
    $this->course->update(['access_duration_days' => 30]);

    sendWebhook($this->order)->assertOk();

    expect(Enrollment::sole()->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('buying again reactivates an expired enrollment instead of duplicating it', function () {
    Enrollment::factory()->expired()->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);

    sendWebhook($this->order)->assertOk();

    expect(Enrollment::sole())
        ->status->toBe(EnrollmentStatus::Active)
        ->order_id->toBe($this->order->id);
});

test('unknown orders answer 404', function () {
    $ghost = Order::factory()->make(['order_number' => 'PXR-20260101-GHOST1', 'total' => 199_000]);

    sendWebhook($ghost)->assertNotFound();
    expect(WebhookEvent::count())->toBe(0);
});

test('reconciliation applies the Status API answer through the same pipeline', function () {
    $gateway = app(FakeGateway::class);
    $gateway->setStatus($this->order, $gateway->notification($this->order, 'settlement'));

    app(ReconcileOrder::class)->handle($this->order);
    app(ReconcileOrder::class)->handle($this->order);

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Enrollment::count())->toBe(1)
        ->and(WebhookEvent::count())->toBe(1);
});

test('reconciling an order without a gateway transaction changes nothing', function () {
    expect(app(ReconcileOrder::class)->handle($this->order)->status)->toBe(OrderStatus::Pending);
});

test('the owner can trigger a status check, others cannot', function () {
    $gateway = app(FakeGateway::class);
    $gateway->setStatus($this->order, $gateway->notification($this->order, 'settlement'));

    $this->actingAs(User::factory()->create()->assignRole('student'))
        ->post(route('student.orders.reconcile', $this->order))
        ->assertNotFound();

    $this->actingAs($this->student)->post(route('student.orders.reconcile', $this->order))->assertRedirect();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid);
});

test('the browser return URL is never proof of payment', function () {
    // Visiting the order page with Midtrans-style query parameters does nothing.
    $this->actingAs($this->student)
        ->get(route('student.orders.show', $this->order).'?transaction_status=settlement&status_code=200')
        ->assertOk();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Pending)->and(Enrollment::count())->toBe(0);
});

test('the fake payment simulator goes through the signed webhook pipeline', function () {
    $this->actingAs($this->student)
        ->post(route('student.orders.simulate', $this->order), ['status' => 'settlement'])
        ->assertRedirect();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(WebhookEvent::sole()->gateway)->toBe('fake');
});

test('the processing result constants are stable', function () {
    expect(ApplyPaymentNotification::PROCESSED)->toBe('processed')->and(ApplyPaymentNotification::DUPLICATE)->toBe('duplicate');
});
