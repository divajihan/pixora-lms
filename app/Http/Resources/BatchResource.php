<?php

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Batch
 */
class BatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $taken = $this->seatsTaken();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'registration_closes_on' => $this->registration_closes_on?->toDateString(),
            'capacity' => $this->capacity,
            'is_published' => $this->is_published,
            'status' => $this->status()->value,
            'status_label' => $this->status()->label(),
            'seats_taken' => $taken,
            'seats_left' => $this->seatsLeft(),
            // Payments are never refused for a full batch; admins see the overflow here.
            'over_capacity' => $this->capacity !== null && $taken > $this->capacity,
            'is_open' => $this->isOpenForRegistration(),
            'enrollments_count' => $this->whenCounted('enrollments'),
        ];
    }
}
