<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates (or reuses) a pending order for one paid course.
 *
 * - Only published, paid courses.
 * - Rejected when the user already has access (accessible enrollment).
 * - Reuses the user's pending, not-yet-expired order for the same course.
 * - Prices ALWAYS come from the database, never from the request.
 */
class CreateOrder
{
    public function __construct(private readonly StartPayment $startPayment) {}

    public function handle(User $user, Course $course): Order
    {
        if (! $course->isPublished()) {
            throw ValidationException::withMessages(['course' => 'Kursus ini belum tersedia untuk dibeli.']);
        }

        if ($course->isFree()) {
            throw ValidationException::withMessages(['course' => 'Kursus ini gratis. Gunakan tombol "Ambil gratis".']);
        }

        if ($user->enrollments()->where('course_id', $course->id)->accessible()->exists()) {
            throw ValidationException::withMessages(['course' => 'Anda sudah terdaftar di kursus ini.']);
        }

        $order = DB::transaction(function () use ($user, $course): Order {
            // Serialise checkouts of the same user so double clicks reuse one order.
            User::whereKey($user->id)->lockForUpdate()->first();

            $existing = $user->orders()
                ->where('status', OrderStatus::Pending)
                ->where('expires_at', '>', now())
                ->whereHas('items', fn (Builder $query) => $query->where('course_id', $course->id))
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $order = $user->orders()->create([
                'order_number' => $this->orderNumber(),
                'subtotal' => $course->price,
                'discount' => 0,
                'total' => $course->price,
                'status' => OrderStatus::Pending,
                'expires_at' => now()->addHours((int) config('pixora.payments.order_expiry_hours', 24)),
            ]);

            $order->items()->create([
                'course_id' => $course->id,
                'title_snapshot' => $course->title,
                'price' => $course->price,
            ]);

            return $order;
        });

        return $this->startPayment->handle($order);
    }

    /**
     * PXR-YYYYMMDD-XXXXXX (no ambiguous characters 0/O, 1/I/L).
     */
    private function orderNumber(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $number = 'PXR-'.now()->format('Ymd').'-'.$suffix;
        } while (Order::where('order_number', $number)->exists());

        return Str::upper($number);
    }
}
