<?php

namespace App\Actions\Learning;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Support\Collection;

/**
 * Student-facing outline of a course for one enrollment: published lessons in
 * live modules with their status (completed / unlocked / locked) and unlock
 * date. Constant number of queries (modules+lessons, overrides, progress).
 *
 * Contains NO protected content (video, summary, meeting link) — safe to send
 * to the browser for locked lessons too.
 */
class BuildCourseOutline
{
    public function __construct(private readonly ComputeLessonAccess $access) {}

    /**
     * @return array{
     *     modules: array<int, array{id: int, title: string, lessons: array<int, array<string, mixed>>}>,
     *     lessons: Collection<int, Lesson>,
     *     statuses: array<int, array<string, mixed>>,
     *     continue_slug: string|null,
     *     counts: array{total: int, completed: int, unlocked: int}
     * }
     */
    public function handle(Enrollment $enrollment): array
    {
        $enrollment->loadMissing(['course', 'batch']);

        $modules = Module::query()
            ->where('course_id', $enrollment->course_id)
            ->ordered()
            ->with(['lessons' => fn ($query) => $query->published()])
            ->get();

        /** @var Collection<int, Lesson> $lessons */
        $lessons = $modules->flatMap(fn (Module $module) => $module->lessons)->values();

        $access = $this->access->forEnrollment($enrollment, $lessons);

        $completed = $enrollment->lessonProgress()
            ->where('status', LessonProgressStatus::Completed)
            ->pluck('lesson_id')
            ->flip();

        $statuses = [];
        $continue = null;

        foreach ($lessons as $lesson) {
            $lessonAccess = $access[$lesson->id];
            $isCompleted = $completed->has($lesson->id);

            $statuses[$lesson->id] = [
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'type' => $lesson->type->value,
                'duration_seconds' => $lesson->duration_seconds,
                'is_preview' => $lesson->is_preview,
                'day_number' => $enrollment->course->drip_enabled ? $lesson->dayNumber() : null,
                'status' => $isCompleted && $lessonAccess->unlocked ? 'completed' : ($lessonAccess->unlocked ? 'unlocked' : 'locked'),
                'unlock_at' => $lessonAccess->unlockAt?->toIso8601String(),
            ];

            if ($continue === null && $lessonAccess->unlocked && ! $isCompleted) {
                $continue = $lesson->slug;
            }
        }

        return [
            'modules' => $modules
                ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
                ->map(fn (Module $module) => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'lessons' => $module->lessons->map(fn (Lesson $lesson) => $statuses[$lesson->id])->values()->all(),
                ])
                ->values()
                ->all(),
            'lessons' => $lessons,
            'statuses' => $statuses,
            'continue_slug' => $continue,
            'counts' => [
                'total' => $lessons->count(),
                'completed' => collect($statuses)->where('status', 'completed')->count(),
                'unlocked' => collect($statuses)->whereIn('status', ['completed', 'unlocked'])->count(),
            ],
        ];
    }
}
