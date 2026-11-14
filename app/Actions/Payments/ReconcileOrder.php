<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentGatewayException;
use Illuminate\Validation\ValidationException;

/**
 * Asks the gateway's Status API for one order and applies the answer through
 * the same idempotent pipeline as webhooks. Used by the order status page and
 * by admins when a webhook is late or lost. Server-to-server: no browser data
 * is trusted.
 */
class ReconcileOrder
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyPaymentNotification $apply,
    ) {}

    public function handle(Order $order): Order
    {
        try {
            $notification = $this->gateway->fetchStatus($order);
        } catch (PaymentGatewayException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }

        if ($notification !== null && $notification->orderNumber === $order->order_number) {
            try {
                $this->apply->handle($notification, $this->gateway->key());
            } catch (PaymentProcessingException $exception) {
                throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
            }
        }

        return $order->refresh();
    }
}
