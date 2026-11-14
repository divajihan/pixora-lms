<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentGatewayException;
use Illuminate\Validation\ValidationException;

/**
 * Makes sure a payable order has a gateway checkout session (Snap token).
 * The token is stored on the order and reused while the order is pending
 * (Midtrans rejects a second transaction for the same order_id).
 */
class StartPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function handle(Order $order): Order
    {
        if (! $order->isPayable() || $order->payment_token !== null) {
            return $order;
        }

        try {
            $session = $this->gateway->createSession($order);
        } catch (PaymentGatewayException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }

        $order->forceFill([
            'payment_token' => $session->token,
            'payment_url' => $session->redirectUrl,
        ])->save();

        return $order;
    }
}
