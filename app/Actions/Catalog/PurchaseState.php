<?php

namespace App\Actions\Catalog;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Batch;
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
     *     order_url: string|null,
     *     batches: array<int, array<string, mixed>>
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
            'batches' => $this->batches($course),
        ];

        // Guests see buy/free; the checkout route sends them to login and back.
        if ($user === null) {
            return ['state' => $course->isFree() ? 'free' : 'buy', ...$base];
        }

        if ($user->enrollments()->where('course_id', $course->id)->accessible()->exists()) {
            return ['state' => 'enrolled', ...$base, 'learn_url' => route('learn.course', $course)];
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

    /**
     * Published batches that have not finished yet, with seats and whether
     * registration is open (drip courses only).
     *
     * @return array<int, array<string, mixed>>
     */
    public function batches(Course $course): array
    {
        if (! $course->drip_enabled) {
            return [];
        }

        return Batch::query()
            ->where('course_id', $course->id)
            ->published()
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->withSeatsTaken()
            ->orderBy('starts_on')
            ->get()
            ->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'starts_on' => $batch->starts_on->toDateString(),
                'ends_on' => $batch->ends_on->toDateString(),
                'registration_closes_on' => $batch->registration_closes_on?->toDateString(),
                'status' => $batch->status()->value,
                'status_label' => $batch->status()->label(),
                'seats_left' => $batch->seatsLeft(),
                'is_open' => $batch->isOpenForRegistration(),
                'closed_reason' => $batch->registrationBlocker(),
            ])
            ->values()
            ->all();
    }
}
