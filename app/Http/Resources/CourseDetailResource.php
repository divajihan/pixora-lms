<?php

namespace App\Http\Resources;

use App\Enums\CourseInstructorRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public course detail page data.
 *
 * @mixin Course
 */
class CourseDetailResource extends JsonResource
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
            // Plain text; rendered with preserved line breaks (never as HTML).
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'price' => $this->price,
            'is_free' => $this->is_free,
            'access_duration_days' => $this->access_duration_days,
            'certificate_enabled' => $this->certificate_enabled,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'instructors' => $this->whenLoaded('instructors', fn () => $this->instructors
                ->sortBy(fn (User $user) => $user->getRelationValue('assignment')?->role === CourseInstructorRole::Lead ? 0 : 1)
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'role' => $user->getRelationValue('assignment')?->role?->label(),
                    'headline' => $user->instructorProfile?->headline,
                    'bio' => $user->instructorProfile?->bio,
                ])
                ->values()
                ->all()),
        ];
    }
}
