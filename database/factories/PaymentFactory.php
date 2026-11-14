<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'midtrans',
            'external_id' => fake()->uuid(),
            'payment_type' => 'bank_transfer',
            'gross_amount' => 199_000,
            'status' => PaymentStatus::Pending,
            'raw_payload' => [],
        ];
    }
}
