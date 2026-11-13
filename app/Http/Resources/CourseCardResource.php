<?php

namespace App\Http\Resources;

use App\Enums\CourseInstructorRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Course summary for catalog cards. Never exposes drafts' internals or video data.
 *
 * @mixin Course
 */
class CourseCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'thumbnail_url' => $this->thumbnail_url,
            'price' => $this->price,
            'is_free' => $this->is_free,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'instructors' => $this->whenLoaded('instructors', fn () => $this->instructors
                ->sortBy(fn (User $user) => $user->getRelationValue('assignment')?->role === CourseInstructorRole::Lead ? 0 : 1)
                ->map(fn (User $user) => $user->name)
                ->values()
                ->all()),
        ];
    }
}
