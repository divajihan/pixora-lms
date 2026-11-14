<?php

namespace App\Actions\Payments;

use RuntimeException;

/**
 * A notification that must not be applied (unknown order, amount mismatch).
 * $httpStatus is what the webhook answers.
 */
class PaymentProcessingException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public static function unknownOrder(): self
    {
        return new self('Order tidak ditemukan.', 404);
    }

    public static function amountMismatch(): self
    {
        return new self('Nominal pembayaran tidak sesuai dengan total order.', 422);
    }
}
