<?php

namespace App\Actions\Payments;

use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentGatewayException;

/**
 * Entry point for gateway notifications (POST /webhooks/midtrans).
 * Returns the HTTP status to answer with; never trusts unsigned input.
 */
class HandlePaymentWebhook
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyPaymentNotification $apply,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, message: string}
     */
    public function handle(array $payload): array
    {
        if (! $this->gateway->verifySignature($payload)) {
            // Only the order id is logged — never the payload or keys.
            logger()->warning('Rejected payment notification: invalid signature', [
                'order_number' => is_scalar($payload['order_id'] ?? null) ? (string) $payload['order_id'] : null,
                'gateway' => $this->gateway->key(),
            ]);

            return ['status' => 403, 'message' => 'Invalid signature'];
        }

        try {
            $notification = $this->gateway->parseNotification($payload);
            $result = $this->apply->handle($notification, $this->gateway->key());
        } catch (PaymentGatewayException $exception) {
            return ['status' => 400, 'message' => $exception->getMessage()];
        } catch (PaymentProcessingException $exception) {
            return ['status' => $exception->httpStatus, 'message' => $exception->getMessage()];
        }

        return ['status' => 200, 'message' => $result];
    }
}
