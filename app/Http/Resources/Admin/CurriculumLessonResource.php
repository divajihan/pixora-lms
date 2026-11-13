<?php

namespace App\Http\Resources\Admin;

use App\Models\Lesson;
use App\Services\Video\VideoProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full lesson data for staff screens (admin curriculum builder, instructor
 * read-only view). Includes the video URL — never use it for public/student pages.
 *
 * @mixin Lesson
 */
class CurriculumLessonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $live = $this->relationLoaded('liveSession') ? $this->liveSession : null;

        return [
            'id' => $this->id,
            'module_id' => $this->module_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'is_preview' => $this->is_preview,
            'duration_seconds' => $this->duration_seconds,
            'drip_day_offset' => $this->drip_day_offset,
            'summary_html' => $this->summary_html,
            'video_url' => $this->video_ref ? app(VideoProvider::class)->watchUrl($this->video_ref) : null,
            'live' => $live ? [
                'starts_at' => $live->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $live->ends_at?->format('Y-m-d\TH:i'),
                'platform' => $live->platform,
                'meeting_url' => $live->meeting_url,
                'notes' => $live->notes,
            ] : null,
        ];
    }
}
