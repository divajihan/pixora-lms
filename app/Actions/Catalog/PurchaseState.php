<?php

namespace App\Actions\Catalog;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the call-to-action on the public course page should be for the viewer.
 */
class PurchaseState
{
    /**
     * @return array{
     *     state: 'enrolled'|'pending_order'|'buy'|'free'|'not_student',
     *     price: int,
     *     is_free: bool,
     *     checkout_url: string,
     *     learn_url: string|null,
     *     order_url: string|null
     * }
     */
    public function handle(?User $user, Course $course): array
    {
        $base = [
            'price' => $course->price,
            'is_free' => $course->isFree(),
            'checkout_url' => route('checkout.show', $course),
            'learn_url' => null,
            'order_url' => null,
        ];

        // Guests see buy/free; the checkout route sends them to login and back.
        if ($user === null) {
            return ['state' => $course->isFree() ? 'free' : 'buy', ...$base];
        }

        if ($user->enrollments()->where('course_id', $course->id)->accessible()->exists()) {
            return ['state' => 'enrolled', ...$base, 'learn_url' => route('student.courses.learn', $course)];
        }

        if (! $user->hasRole(Role::Student->value)) {
            return ['state' => 'not_student', ...$base];
        }

        $pending = $user->orders()
            ->where('status', OrderStatus::Pending)
            ->where('expires_at', '>', now())
            ->whereHas('items', fn (Builder $query) => $query->where('course_id', $course->id))
            ->latest('id')
            ->value('order_number');

        if ($pending !== null && ! $course->isFree()) {
            return ['state' => 'pending_order', ...$base, 'order_url' => route('student.orders.show', $pending)];
        }

        return ['state' => $course->isFree() ? 'free' : 'buy', ...$base];
    }
}
