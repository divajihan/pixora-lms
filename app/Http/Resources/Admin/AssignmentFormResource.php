<?php

namespace App\Http\Resources\Admin;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Assignment settings for the admin curriculum builder.
 *
 * @mixin Assignment
 */
class AssignmentFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'is_final_project' => $this->is_final_project,
            'title' => $this->title,
            'instructions_html' => $this->instructions_html,
            'due_offset_days' => $this->due_offset_days,
            'max_images' => $this->max_images,
            'is_required' => $this->is_required,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'submissions_count' => $this->whenCounted('submissions'),
        ];
    }
}
