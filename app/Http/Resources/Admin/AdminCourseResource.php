<?php

namespace App\Http\Resources\Admin;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Course data for the admin list and edit form.
 *
 * @mixin Course
 */
class AdminCourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'thumbnail_url' => $this->thumbnail_url,
            'price' => $this->price,
            'is_free' => $this->is_free,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'drip_enabled' => $this->drip_enabled,
            'access_duration_days' => $this->access_duration_days,
            'certificate_enabled' => $this->certificate_enabled,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'modules_count' => $this->whenCounted('modules'),
            'lessons_count' => $this->whenCounted('lessons'),
            'instructors' => $this->whenLoaded('instructors', fn () => $this->instructors->pluck('name')->all()),
        ];
    }
}
