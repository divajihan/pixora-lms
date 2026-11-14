<?php

namespace App\Services\Payment;

use App\Models\Order;

/**
 * Abstraction over the payment provider (Midtrans Snap now; Xendit later).
 * Implementations: MidtransGateway, FakeGateway (local/tests). Selected by
 * config('pixora.payments.gateway') in AppServiceProvider.
 *
 * Security rules for callers:
 * - Only trust payment results that come from a verified notification
 *   (verifySignature) or from the gateway's server API (fetchStatus) —
 *   never from the browser redirect.
 * - Never log keys or full payloads.
 */
interface PaymentGateway
{
    /**
     * Key stored in payments.gateway and webhook_events.gateway (e.g. "midtrans").
     */
    public function key(): string;

    /**
     * Starts a checkout session (Snap token) for a pending order.
     *
     * @throws PaymentGatewayException
     */
    public function createSession(Order $order): PaymentSession;

    /**
     * Verifies the notification signature (constant-time comparison).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool;

    /**
     * Normalises a (verified) notification or status payload.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PaymentGatewayException when required fields are missing
     */
    public function parseNotification(array $payload): PaymentNotification;

    /**
     * Current transaction status from the gateway's server API, or null when
     * the gateway has no transaction for this order yet.
     *
     * @throws PaymentGatewayException
     */
    public function fetchStatus(Order $order): ?PaymentNotification;

    /**
     * Public settings for the browser (e.g. Snap.js URL and client key).
     *
     * @return array{driver: string, script_url: string|null, client_key: string|null}
     */
    public function clientConfig(): array;
}
