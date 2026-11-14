<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Received gateway notifications; unique (gateway, event_id) makes processing idempotent.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_id
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 */
#[Fillable(['gateway', 'event_id', 'payload', 'processed_at'])]
class WebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
