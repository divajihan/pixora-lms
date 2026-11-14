<?php

namespace App\Actions\Orders;

use App\Actions\Enrollments\GrantEnrollment;
use App\Actions\Payments\ApplyPaymentNotification;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PaymentSucceeded;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin: mark an order as paid manually (bank transfer, lost webhook).
 * Audit trail: a "manual" payments row with the admin, time and note
 * (no activity-log package installed).
 */
class MarkOrderPaid
{
    public function __construct(private readonly GrantEnrollment $grant) {}

    public function handle(Order $order, User $admin, string $note): Order
    {
        return DB::transaction(function () use ($order, $admin, $note): Order {
            /** @var Order $order */
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
                throw ValidationException::withMessages(['note' => "Order berstatus \"{$order->status->label()}\" tidak dapat ditandai lunas."]);
            }

            $order->payments()->create([
                'gateway' => 'manual',
                'external_id' => 'MANUAL-PAID-'.$order->order_number.'-'.now()->format('YmdHis'),
                'payment_type' => 'manual',
                'gross_amount' => $order->total,
                'status' => PaymentStatus::Success,
                'raw_payload' => ['action' => 'mark_paid', 'note' => $note, 'admin_id' => $admin->id, 'admin_name' => $admin->name, 'at' => now()->toIso8601String()],
                'paid_at' => now(),
            ]);

            $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();

            ApplyPaymentNotification::grantFor($order, $this->grant);

            DB::afterCommit(fn () => $order->user->notify(new PaymentSucceeded($order)));

            return $order;
        });
    }
}
