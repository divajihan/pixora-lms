<?php

namespace App\Actions\Catalog;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\Video\VideoProvider;

/**
 * Curriculum shown on the public course page.
 *
 * SECURITY: only published lessons (in modules that are not deleted) are
 * listed, and video data (embed URL) is included ONLY for preview lessons.
 * video_ref itself is never exposed here. Covered by CourseCurriculumTest.
 */
class PublicCurriculum
{
    public function __construct(private readonly VideoProvider $videoProvider) {}

    /**
     * @return array{
     *     modules: array<int, array{id: int, title: string, lessons: array<int, array<string, mixed>>}>,
     *     lesson_count: int,
     *     duration_seconds: int
     * }
     */
    public function handle(Course $course): array
    {
        $modules = $course->modules()
            ->with(['lessons' => fn ($query) => $query->published()])
            ->get()
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->values();

        $lessons = $modules->flatMap(fn (Module $module) => $module->lessons);

        return [
            'modules' => $modules->map(fn (Module $module) => [
                'id' => $module->id,
                'title' => $module->title,
                'lessons' => $module->lessons->map(fn (Lesson $lesson) => $this->lesson($lesson))->values()->all(),
            ])->all(),
            'lesson_count' => $lessons->count(),
            'duration_seconds' => (int) $lessons->sum(fn (Lesson $lesson) => $lesson->duration_seconds ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lesson(Lesson $lesson): array
    {
        $data = [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'type' => $lesson->type->value,
            'duration_seconds' => $lesson->duration_seconds,
            'is_preview' => $lesson->is_preview,
        ];

        // Only preview lessons may carry anything that reveals the video.
        if ($lesson->is_preview && $lesson->video_ref !== null) {
            $data['preview'] = [
                'embed_url' => $this->videoProvider->embedUrl($lesson->video_ref),
                'thumbnail_url' => $this->videoProvider->thumbnailUrl($lesson->video_ref),
            ];
        }

        return $data;
    }
}
