<?php

namespace App\Services\Payment;

final readonly class PaymentSession
{
    public function __construct(
        public string $token,
        public ?string $redirectUrl = null,
    ) {}
}
