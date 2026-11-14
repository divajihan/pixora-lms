<?php

namespace App\Http\Resources;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order data for students (own orders) and order managers. Never exposes the
 * raw gateway payload; for manual admin actions only the note/admin name.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'is_payable' => $this->isPayable(),
            'created_at' => $this->created_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'customer' => $this->whenLoaded('user', fn () => ['name' => $this->user->name, 'email' => $this->user->email]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'course_id' => $item->course_id,
                'title' => $item->title_snapshot,
                'price' => $item->price,
                'course_slug' => $item->course?->slug,
                'batch' => $item->batch_id && $item->relationLoaded('batch') && $item->batch ? [
                    'id' => $item->batch->id,
                    'name' => $item->batch->name,
                    'starts_on' => $item->batch->starts_on->toDateString(),
                    'ends_on' => $item->batch->ends_on->toDateString(),
                ] : null,
            ])->values()->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'gateway' => $payment->gateway,
                'external_id' => $payment->external_id,
                'payment_type' => $payment->payment_type,
                'gross_amount' => $payment->gross_amount,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'raw_status' => $payment->gateway === 'manual' ? null : ($payment->raw_payload['transaction_status'] ?? null),
                'note' => $payment->gateway === 'manual' ? ($payment->raw_payload['note'] ?? null) : null,
                'admin_name' => $payment->gateway === 'manual' ? ($payment->raw_payload['admin_name'] ?? null) : null,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'created_at' => $payment->created_at?->toIso8601String(),
                'updated_at' => $payment->updated_at?->toIso8601String(),
            ])->values()->all()),
            'enrollments' => $this->whenLoaded('enrollments', fn () => $this->enrollments->map(fn (Enrollment $enrollment) => [
                'id' => $enrollment->id,
                'course_id' => $enrollment->course_id,
                'status' => $enrollment->status->value,
                'status_label' => $enrollment->status->label(),
                'expires_at' => $enrollment->expires_at?->toIso8601String(),
                'is_accessible' => $enrollment->grantsAccess(),
            ])->values()->all()),
            'has_access' => $this->whenLoaded('enrollments', fn () => $this->enrollments->contains(
                fn (Enrollment $enrollment) => $enrollment->status === EnrollmentStatus::Active && $enrollment->grantsAccess()
            )),
        ];
    }
}
