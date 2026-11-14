<?php

namespace App\Actions\Orders;

use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin: record a refund and revoke access. The money itself is refunded
 * manually in the Midtrans dashboard (the UI says so). Audit trail: a
 * "manual" refunded payments row with the admin, time and note.
 */
class RefundOrder
{
    public function handle(Order $order, User $admin, string $note): Order
    {
        return DB::transaction(function () use ($order, $admin, $note): Order {
            /** @var Order $order */
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Paid) {
                throw ValidationException::withMessages(['note' => 'Hanya order lunas yang dapat di-refund.']);
            }

            $order->payments()->create([
                'gateway' => 'manual',
                'external_id' => 'MANUAL-REFUND-'.$order->order_number.'-'.now()->format('YmdHis'),
                'payment_type' => 'manual',
                'gross_amount' => $order->total,
                'status' => PaymentStatus::Refunded,
                'raw_payload' => ['action' => 'refund', 'note' => $note, 'admin_id' => $admin->id, 'admin_name' => $admin->name, 'at' => now()->toIso8601String()],
            ]);

            $order->forceFill(['status' => OrderStatus::Refunded])->save();

            $order->enrollments()
                ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed, EnrollmentStatus::Expired])
                ->update(['status' => EnrollmentStatus::Revoked, 'updated_at' => now()]);

            return $order;
        });
    }
}
