<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;

/**
 * Midtrans notification/status payload helpers (shared by MidtransGateway and
 * FakeGateway, which speaks the same format).
 *
 * @see https://docs.midtrans.com/docs/https-notification-webhooks
 */
final class MidtransPayload
{
    /**
     * SHA512(order_id + status_code + gross_amount + server_key).
     */
    public static function signature(string $orderId, string $statusCode, string $grossAmount, string $key): string
    {
        return hash('sha512', $orderId.$statusCode.$grossAmount.$key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function verify(array $payload, string $key): bool
    {
        if ($key === '') {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field])) {
                return false;
            }
        }

        $expected = self::signature($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $key);

        return hash_equals($expected, $payload['signature_key']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function parse(array $payload): PaymentNotification
    {
        foreach (['order_id', 'transaction_id', 'transaction_status', 'status_code', 'gross_amount'] as $field) {
            if (! isset($payload[$field]) || ! is_scalar($payload[$field]) || (string) $payload[$field] === '') {
                throw new PaymentGatewayException("Notifikasi pembayaran tidak lengkap: {$field}.");
            }
        }

        $transactionStatus = (string) $payload['transaction_status'];
        $fraudStatus = isset($payload['fraud_status']) && is_scalar($payload['fraud_status']) ? (string) $payload['fraud_status'] : null;

        return new PaymentNotification(
            orderNumber: (string) $payload['order_id'],
            transactionId: (string) $payload['transaction_id'],
            eventId: $payload['transaction_id'].':'.$transactionStatus.':'.$payload['status_code'],
            status: self::status($transactionStatus, $fraudStatus),
            grossAmount: (string) $payload['gross_amount'],
            paymentType: isset($payload['payment_type']) && is_scalar($payload['payment_type']) ? (string) $payload['payment_type'] : null,
            rawStatus: $transactionStatus.($fraudStatus ? "/{$fraudStatus}" : ''),
            payload: $payload,
        );
    }

    /**
     * Maps Midtrans transaction_status (+ fraud_status for card captures).
     */
    public static function status(string $transactionStatus, ?string $fraudStatus): PaymentStatus
    {
        return match ($transactionStatus) {
            'settlement' => PaymentStatus::Success,
            // Card payments: only "accept" is a success; "challenge" waits for review.
            'capture' => match ($fraudStatus) {
                'accept' => PaymentStatus::Success,
                'deny' => PaymentStatus::Failed,
                default => PaymentStatus::Pending,
            },
            'pending', 'authorize' => PaymentStatus::Pending,
            'deny', 'failure' => PaymentStatus::Failed,
            'cancel' => PaymentStatus::Cancelled,
            'expire' => PaymentStatus::Expired,
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };
    }
}
