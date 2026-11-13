<?php

namespace App\Services\Payment;

/**
 * Abstraction over the payment provider (Midtrans Snap first, Xendit later).
 *
 * Intentionally empty in Phase 0. Methods (create charge, verify webhook
 * signature, parse notification, etc.) are defined in Phase 3.
 */
interface PaymentGateway
{
    //
}
