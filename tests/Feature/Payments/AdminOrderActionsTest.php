<?php

use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Order;
use App\Notifications\PaymentSucceeded;
use App\Services\Payment\FakeGateway;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = actingAsRole(Role::Admin);
    [$this->student, $this->course] = studentAndPaidCourse();
    $this->order = Order::factory()->forCourse($this->course)->create(['user_id' => $this->student->id]);
});

test('marking an order paid manually enrolls the student and records the note', function () {
    $this->post(route('admin.orders.mark-paid', $this->order), ['note' => 'Transfer BCA ref 12345'])->assertSessionHasNoErrors();

    $order = $this->order->refresh();
    $payment = $order->payments()->sole();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and(Enrollment::sole()->order_id)->toBe($order->id)
        ->and($payment->gateway)->toBe('manual')
        ->and($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->raw_payload['note'])->toBe('Transfer BCA ref 12345')
        ->and($payment->raw_payload['admin_id'])->toBe($this->admin->id);

    Notification::assertSentTo($this->student, PaymentSucceeded::class);
});

test('a note is required for admin actions', function () {
    $this->post(route('admin.orders.mark-paid', $this->order), ['note' => ''])->assertSessionHasErrors('note');

    expect($this->order->refresh()->status)->toBe(OrderStatus::Pending);
});

test('paid orders cannot be marked paid again', function () {
    $this->post(route('admin.orders.mark-paid', $this->order), ['note' => 'Pertama kali']);
    $this->post(route('admin.orders.mark-paid', $this->order), ['note' => 'Kedua kali'])->assertSessionHasErrors('note');

    expect($this->order->payments()->count())->toBe(1)->and(Enrollment::count())->toBe(1);
});

test('refund revokes access and is recorded', function () {
    sendWebhook($this->order)->assertOk();

    $this->post(route('admin.orders.refund', $this->order), ['note' => 'Siswa membatalkan dalam 3 hari'])->assertSessionHasNoErrors();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and(Enrollment::sole()->status)->toBe(EnrollmentStatus::Revoked)
        ->and($this->order->payments()->where('gateway', 'manual')->sole()->status)->toBe(PaymentStatus::Refunded);

    // A late success notification cannot re-open a refunded order.
    sendWebhook($this->order, 'settlement', ['transaction_id' => 'late-success'])->assertOk();
    expect($this->order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and(Enrollment::sole()->status)->toBe(EnrollmentStatus::Revoked);
});

test('only paid orders can be refunded', function () {
    $this->post(route('admin.orders.refund', $this->order), ['note' => 'Coba refund'])
        ->assertSessionHasErrors(['note' => 'Hanya order lunas yang dapat di-refund.']);
});

test('admins can reconcile an order with the gateway', function () {
    $gateway = app(FakeGateway::class);
    $gateway->setStatus($this->order, $gateway->notification($this->order, 'settlement'));

    $this->post(route('admin.orders.reconcile', $this->order))->assertSessionHasNoErrors();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid);
});
