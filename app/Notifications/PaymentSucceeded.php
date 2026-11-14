<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent (queued) when an order becomes paid.
 */
class PaymentSucceeded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items.course');
        $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');

        $message = (new MailMessage)
            ->subject("Pembayaran berhasil — {$order->order_number}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Terima kasih, pembayaran Anda sudah kami terima. Berikut ringkasan pesanan Anda:')
            ->line("**Nomor pesanan:** {$order->order_number}");

        $order->items->each(function (OrderItem $item) use ($message, $rupiah): void {
            $message->line("- {$item->title_snapshot} — {$rupiah($item->price)}");
        });

        $message->line("**Total:** {$rupiah($order->total)}");

        $first = $order->items->first()?->course;

        if ($first !== null) {
            $message->action('Mulai belajar', route('learn.course', $first));
        }

        return $message
            ->line('Anda juga dapat melihat invoice di menu Pesanan pada akun Anda.')
            ->salutation('Salam, Tim '.config('app.name'));
    }
}
