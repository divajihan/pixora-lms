<?php

namespace App\Actions\Payments;

use App\Actions\Enrollments\GrantEnrollment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Notifications\PaymentSucceeded;
use App\Services\Payment\PaymentNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Applies a TRUSTED payment notification (signature already verified, or
 * fetched from the gateway's server API) to its order. Idempotent:
 *
 * 1. webhook_events (gateway, event_id) is inserted inside the transaction —
 *    a duplicate insert means "already processed" and nothing else happens.
 * 2. The order row is locked (SELECT ... FOR UPDATE).
 * 3. gross_amount must equal the order total, otherwise everything rolls back.
 * 4. The payment attempt is upserted by external_id; the order status moves
 *    forward only (a paid order is never downgraded by a late notification).
 * 5. On success: order paid + enrollment via GrantEnrollment (unique per
 *    user/course). The confirmation email is queued after commit.
 */
class ApplyPaymentNotification
{
    public const PROCESSED = 'processed';

    public const DUPLICATE = 'duplicate';

    public function __construct(private readonly GrantEnrollment $grantEnrollment) {}

    /**
     * @throws PaymentProcessingException
     */
    public function handle(PaymentNotification $notification, string $gateway): string
    {
        $orderId = Order::where('order_number', $notification->orderNumber)->value('id');

        if ($orderId === null) {
            logger()->warning('Payment notification for unknown order', ['order_number' => $notification->orderNumber, 'gateway' => $gateway]);

            throw PaymentProcessingException::unknownOrder();
        }

        return DB::transaction(function () use ($notification, $gateway, $orderId): string {
            try {
                $event = WebhookEvent::create([
                    'gateway' => $gateway,
                    'event_id' => $notification->eventId,
                    'payload' => $notification->payload,
                ]);
            } catch (UniqueConstraintViolationException) {
                return self::DUPLICATE;
            }

            /** @var Order $order */
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();

            if (! $notification->amountMatches($order->total)) {
                logger()->warning('Payment amount mismatch', [
                    'order_number' => $order->order_number,
                    'expected' => $order->total,
                    'received' => $notification->grossAmount,
                    'gateway' => $gateway,
                ]);

                throw PaymentProcessingException::amountMismatch();
            }

            Payment::updateOrCreate(['external_id' => $notification->transactionId], [
                'order_id' => $order->id,
                'gateway' => $gateway,
                'payment_type' => $notification->paymentType,
                'gross_amount' => $order->total,
                'status' => $notification->status,
                'raw_payload' => $notification->payload,
                'paid_at' => $notification->isSuccess() ? now() : null,
            ]);

            $this->transition($order, $notification->status);

            $event->forceFill(['processed_at' => now()])->save();

            return self::PROCESSED;
        });
    }

    private function transition(Order $order, PaymentStatus $status): void
    {
        // Paid and refunded orders are final for gateway notifications.
        if ($order->status === OrderStatus::Refunded) {
            return;
        }

        if ($order->status === OrderStatus::Paid) {
            if ($status !== PaymentStatus::Success) {
                logger()->info('Ignored non-success notification for paid order', ['order_number' => $order->order_number, 'status' => $status->value]);
            }

            return;
        }

        match ($status) {
            // A successful payment wins even if the order already failed/expired locally.
            PaymentStatus::Success => $this->markPaid($order),
            PaymentStatus::Failed, PaymentStatus::Cancelled => $order->status === OrderStatus::Pending
                ? $order->forceFill(['status' => OrderStatus::Failed])->save()
                : null,
            PaymentStatus::Expired => $order->status === OrderStatus::Pending
                ? $order->forceFill(['status' => OrderStatus::Expired])->save()
                : null,
            PaymentStatus::Pending, PaymentStatus::Refunded => null,
        };
    }

    private function markPaid(Order $order): void
    {
        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();

        self::grantFor($order, $this->grantEnrollment);

        DB::afterCommit(fn () => $order->user->notify(new PaymentSucceeded($order)));
    }

    /**
     * Enrolls the buyer in every course of a paid order.
     */
    public static function grantFor(Order $order, GrantEnrollment $grant): void
    {
        $order->loadMissing(['user', 'items.course']);

        $order->items->each(function (OrderItem $item) use ($order, $grant): void {
            if ($item->course instanceof Course) {
                $grant->handle($order->user, $item->course, $order);
            }
        });
    }
}
