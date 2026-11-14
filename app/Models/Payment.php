<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One payment attempt (gateway transaction) or a manual admin action.
 *
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property string $external_id
 * @property string|null $payment_type
 * @property int $gross_amount
 * @property PaymentStatus $status
 * @property array<string, mixed>|null $raw_payload
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_id', 'gateway', 'external_id', 'payment_type', 'gross_amount', 'status', 'raw_payload', 'paid_at'])]
#[Hidden(['raw_payload'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'status' => PaymentStatus::class,
            'raw_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
