<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: a pending order (use forCourse() to add the item).
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'PXR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'subtotal' => 199_000,
            'discount' => 0,
            'total' => 199_000,
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * Adds an order item for the course with its current price as snapshot.
     */
    public function forCourse(Course $course): static
    {
        return $this->state(fn () => [
            'subtotal' => $course->price,
            'total' => $course->price,
        ])->afterCreating(fn (Order $order) => $order->items()->create([
            'course_id' => $course->id,
            'title_snapshot' => $course->title,
            'price' => $course->price,
        ]));
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Paid, 'paid_at' => now()]);
    }

    public function expiredPending(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Pending, 'expires_at' => now()->subMinute()]);
    }
}
