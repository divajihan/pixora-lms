<?php

namespace App\Actions\Curriculum;

use App\Enums\LessonStatus;
use App\Http\Resources\Admin\CurriculumLessonResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;

/**
 * Curriculum (all modules and lessons, including drafts) plus totals, for the
 * admin builder and the instructor read-only view. Two queries total.
 */
class LoadCurriculum
{
    /**
     * @return array{
     *     modules: array<int, array<string, mixed>>,
     *     totals: array{modules: int, lessons: int, published_lessons: int, duration_seconds: int}
     * }
     */
    public function handle(Course $course): array
    {
        $course->load(['modules.lessons.liveSession']);

        $lessons = $course->modules->flatMap(fn (Module $module) => $module->lessons);

        return [
            'modules' => $course->modules->map(fn (Module $module) => [
                'id' => $module->id,
                'title' => $module->title,
                'lessons' => CurriculumLessonResource::collection($module->lessons)->resolve(),
            ])->values()->all(),
            'totals' => [
                'modules' => $course->modules->count(),
                'lessons' => $lessons->count(),
                'published_lessons' => $lessons->filter(fn (Lesson $lesson) => $lesson->status === LessonStatus::Published)->count(),
                'duration_seconds' => (int) $lessons->sum(fn (Lesson $lesson) => $lesson->duration_seconds ?? 0),
            ],
        ];
    }
}
