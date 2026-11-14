<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;

/**
 * Gateway-independent view of a payment notification / status response.
 */
final readonly class PaymentNotification
{
    /**
     * @param  string  $grossAmount  As sent by the gateway (e.g. "150000.00"); compare with amountMatches().
     * @param  array<string, mixed>  $payload  Raw payload (stored in payments.raw_payload; never logged in full).
     */
    public function __construct(
        public string $orderNumber,
        public string $transactionId,
        public string $eventId,
        public PaymentStatus $status,
        public string $grossAmount,
        public ?string $paymentType,
        public string $rawStatus,
        public array $payload,
    ) {}

    /**
     * Exact rupiah comparison (no float rounding surprises, no partial amounts).
     */
    public function amountMatches(int $expected): bool
    {
        if (preg_match('/^\d+(\.\d{1,2})?$/', $this->grossAmount) !== 1) {
            return false;
        }

        [$whole, $fraction] = array_pad(explode('.', $this->grossAmount, 2), 2, '0');

        return (int) rtrim($fraction, '0') === 0 && (string) (int) $whole === (string) $expected;
    }

    public function isSuccess(): bool
    {
        return $this->status === PaymentStatus::Success;
    }
}
