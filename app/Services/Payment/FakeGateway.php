<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Local development and tests: no network, no keys. Speaks the Midtrans
 * notification format (signed with a fake key) so the real webhook pipeline
 * is exercised end to end.
 */
class FakeGateway implements PaymentGateway
{
    /**
     * Status API responses keyed by order number (set in tests / by the simulator).
     *
     * @var array<string, array<string, mixed>>
     */
    private array $statuses = [];

    public function __construct(private readonly string $signingKey) {}

    public static function fromConfig(): self
    {
        return new self((string) config('pixora.payments.fake_signing_key'));
    }

    public function key(): string
    {
        return 'fake';
    }

    public function createSession(Order $order): PaymentSession
    {
        return new PaymentSession('fake-'.$order->order_number);
    }

    public function verifySignature(array $payload): bool
    {
        return MidtransPayload::verify($payload, $this->signingKey);
    }

    public function parseNotification(array $payload): PaymentNotification
    {
        return MidtransPayload::parse($payload);
    }

    public function fetchStatus(Order $order): ?PaymentNotification
    {
        $payload = $this->statuses[$order->order_number] ?? null;

        return $payload ? MidtransPayload::parse($payload) : null;
    }

    public function clientConfig(): array
    {
        return ['driver' => 'fake', 'script_url' => null, 'client_key' => null];
    }

    /**
     * Builds a signed, Midtrans-shaped notification for an order.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function notification(Order $order, string $transactionStatus = 'settlement', array $overrides = []): array
    {
        $statusCode = match ($transactionStatus) {
            'settlement', 'capture', 'refund' => '200',
            'pending' => '201',
            'deny', 'cancel', 'expire', 'failure' => '202',
            default => '200',
        };

        $payload = array_merge([
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'transaction_status' => $transactionStatus,
            'transaction_id' => 'fake-'.Str::lower($order->order_number),
            'status_message' => 'fake notification',
            'status_code' => $statusCode,
            'payment_type' => 'bank_transfer',
            'order_id' => $order->order_number,
            'gross_amount' => $order->total.'.00',
            'fraud_status' => 'accept',
            'currency' => 'IDR',
        ], $overrides);

        $payload['signature_key'] ??= MidtransPayload::signature(
            (string) $payload['order_id'],
            (string) $payload['status_code'],
            (string) $payload['gross_amount'],
            $this->signingKey,
        );

        return $payload;
    }

    /**
     * Makes fetchStatus() return this payload for the order.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setStatus(Order $order, array $payload): void
    {
        $this->statuses[$order->order_number] = $payload;
    }
}
