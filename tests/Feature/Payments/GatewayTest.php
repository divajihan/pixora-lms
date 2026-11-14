<?php

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Order;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\MidtransGateway;
use App\Services\Payment\MidtransPayload;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function midtrans(): MidtransGateway
{
    return new MidtransGateway(serverKey: 'SB-Mid-server-TEST', clientKey: 'SB-Mid-client-TEST');
}

test('the Midtrans signature is SHA512(order_id + status_code + gross_amount + server_key)', function () {
    $payload = [
        'order_id' => 'PXR-20261114-ABC123',
        'status_code' => '200',
        'gross_amount' => '150000.00',
    ];
    $payload['signature_key'] = hash('sha512', 'PXR-20261114-ABC123'.'200'.'150000.00'.'SB-Mid-server-TEST');

    expect(midtrans()->verifySignature($payload))->toBeTrue()
        ->and(midtrans()->verifySignature([...$payload, 'gross_amount' => '1.00']))->toBeFalse()
        ->and(midtrans()->verifySignature([...$payload, 'signature_key' => str_repeat('0', 128)]))->toBeFalse()
        ->and(midtrans()->verifySignature(['order_id' => 'x']))->toBeFalse()
        ->and(MidtransPayload::verify($payload, ''))->toBeFalse();
});

test('Midtrans statuses are normalised', function (string $status, ?string $fraud, PaymentStatus $expected) {
    expect(MidtransPayload::status($status, $fraud))->toBe($expected);
})->with([
    ['settlement', null, PaymentStatus::Success],
    ['capture', 'accept', PaymentStatus::Success],
    ['capture', 'challenge', PaymentStatus::Pending],
    ['capture', 'deny', PaymentStatus::Failed],
    ['capture', null, PaymentStatus::Pending],
    ['pending', null, PaymentStatus::Pending],
    ['deny', null, PaymentStatus::Failed],
    ['failure', null, PaymentStatus::Failed],
    ['cancel', null, PaymentStatus::Cancelled],
    ['expire', null, PaymentStatus::Expired],
    ['refund', null, PaymentStatus::Refunded],
]);

test('notifications are parsed with a stable event id', function () {
    $notification = MidtransPayload::parse([
        'order_id' => 'PXR-1', 'transaction_id' => 'trx-9', 'transaction_status' => 'settlement',
        'status_code' => '200', 'gross_amount' => '99000.00', 'payment_type' => 'qris',
    ]);

    expect($notification)->toBeInstanceOf(PaymentNotification::class)
        ->and($notification->eventId)->toBe('trx-9:settlement:200')
        ->and($notification->status)->toBe(PaymentStatus::Success)
        ->and($notification->paymentType)->toBe('qris');

    expect(fn () => MidtransPayload::parse(['order_id' => 'PXR-1']))->toThrow(PaymentGatewayException::class);
});

test('gross amounts are compared exactly', function (string $gross, int $total, bool $matches) {
    $notification = MidtransPayload::parse([
        'order_id' => 'PXR-1', 'transaction_id' => 't', 'transaction_status' => 'settlement',
        'status_code' => '200', 'gross_amount' => $gross,
    ]);

    expect($notification->amountMatches($total))->toBe($matches);
})->with([
    ['150000.00', 150000, true],
    ['150000', 150000, true],
    ['150000.5', 150000, false],
    ['149999.99', 150000, false],
    ['1500000.00', 150000, false],
    ['15000O.00', 150000, false],
    ['-150000.00', 150000, false],
]);

test('Snap transactions are created with the server-side total and basic auth', function () {
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'snap-token-1', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1'], 201),
    ]);

    $course = Course::factory()->paid(249_000)->create(['title' => str_repeat('Judul sangat panjang ', 5)]);
    $order = Order::factory()->forCourse($course)->create();

    $session = midtrans()->createSession($order);

    expect($session->token)->toBe('snap-token-1');

    Http::assertSent(function (Request $request) use ($order) {
        return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-TEST:'))
            && $request['transaction_details'] === ['order_id' => $order->order_number, 'gross_amount' => 249_000]
            && $request['item_details'][0]['price'] === 249_000
            && mb_strlen($request['item_details'][0]['name']) <= 50
            && $request['expiry']['unit'] === 'minute';
    });
});

test('Snap failures raise a gateway exception without leaking the key', function () {
    Http::fake(['*' => Http::response(['error_messages' => ['Access denied']], 401)]);
    $order = Order::factory()->forCourse(Course::factory()->paid()->create())->create();

    expect(fn () => midtrans()->createSession($order))->toThrow(PaymentGatewayException::class, 'Gagal membuat sesi pembayaran Midtrans.');
});

test('the status API returns null for unknown transactions and parses known ones', function () {
    $order = Order::factory()->create();

    Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::sequence()
        ->push(['status_code' => '404', 'status_message' => "Transaction doesn't exist."])
        ->push(['status_code' => '200', 'transaction_status' => 'settlement', 'transaction_id' => 'trx-1', 'order_id' => $order->order_number, 'gross_amount' => $order->total.'.00']),
    ]);

    expect(midtrans()->fetchStatus($order))->toBeNull()
        ->and(midtrans()->fetchStatus($order)?->status)->toBe(PaymentStatus::Success);
});

test('the gateway is chosen by config', function () {
    config(['pixora.payments.gateway' => 'fake']);
    app()->forgetInstance(PaymentGateway::class);
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakeGateway::class);

    config(['pixora.payments.gateway' => 'midtrans']);
    app()->forgetInstance(PaymentGateway::class);
    expect(app(PaymentGateway::class))->toBeInstanceOf(MidtransGateway::class)
        ->and(app(PaymentGateway::class)->clientConfig()['script_url'])->toBe('https://app.sandbox.midtrans.com/snap/snap.js');
});

test('the fake gateway can never be used in production', function () {
    app()->detectEnvironment(fn () => 'production');
    app()->forgetInstance(FakeGateway::class);

    expect(fn () => app(FakeGateway::class))->toThrow(RuntimeException::class);
});
