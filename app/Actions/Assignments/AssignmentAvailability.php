<?php

namespace App\Actions\Assignments;

use App\Actions\Learning\ComputeLessonAccess;
use App\Actions\Learning\LessonAccess;
use App\Enums\AssignmentStatus;
use App\Enums\LessonStatus;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * When a student may open (and submit to) an assignment:
 *   - the assignment is published and belongs to the enrollment's course;
 *   - lesson assignment: the lesson is viewable (Phase 4 rules, ComputeLessonAccess);
 *   - final project: the course is accessible and, for a drip course with a
 *     batch, the batch has started (non-drip: from enrollment).
 */
class AssignmentAvailability
{
    public function __construct(private readonly ComputeLessonAccess $lessonAccess) {}

    public function isAvailable(Enrollment $enrollment, Assignment $assignment): bool
    {
        return $this->opensAt($enrollment, $assignment) === true;
    }

    /**
     * true = open now; a date = opens later; false = never for this student
     * (draft, other course, lesson unpublished/archived, enrollment inactive).
     */
    public function opensAt(Enrollment $enrollment, Assignment $assignment): bool|CarbonImmutable
    {
        return $this->forEnrollment($enrollment, collect([$assignment]))[$assignment->id];
    }

    /**
     * Bulk version for one enrollment (one unlock-override query).
     *
     * @param  Collection<int, Assignment>  $assignments  with lesson loaded
     * @return array<int, bool|CarbonImmutable> keyed by assignment id
     */
    public function forEnrollment(Enrollment $enrollment, Collection $assignments): array
    {
        $canAccessCourse = $this->lessonAccess->canAccessCourse($enrollment);
        $lessons = $assignments->map(fn (Assignment $assignment) => $assignment->lesson)
            ->filter(fn (?Lesson $lesson) => $lesson !== null && $lesson->status === LessonStatus::Published)
            ->keyBy('id');
        $lessonAccess = $canAccessCourse ? $this->lessonAccess->forEnrollment($enrollment, $lessons->values()) : [];
        $now = now();

        $result = [];

        foreach ($assignments as $assignment) {
            $result[$assignment->id] = match (true) {
                ! $canAccessCourse,
                $assignment->trashed(),
                $assignment->status !== AssignmentStatus::Published,
                $assignment->course_id !== $enrollment->course_id => false,
                $assignment->lesson_id === null => $this->finalProjectOpensAt($enrollment, $now),
                default => $this->lessonOpensAt($assignment, $lessons, $lessonAccess),
            };
        }

        return $result;
    }

    private function finalProjectOpensAt(Enrollment $enrollment, CarbonImmutable $now): bool|CarbonImmutable
    {
        $batch = $enrollment->course?->drip_enabled && $enrollment->batch_id ? $enrollment->batch : null;

        if ($batch === null) {
            return true;
        }

        $start = $batch->dayStart(0);

        return $start->lte($now) ? true : $start;
    }

    /**
     * @param  Collection<int, Lesson>  $lessons
     * @param  array<int, LessonAccess>  $lessonAccess
     */
    private function lessonOpensAt(Assignment $assignment, Collection $lessons, array $lessonAccess): bool|CarbonImmutable
    {
        $lesson = $lessons->get((int) $assignment->lesson_id);

        // Unpublished or deleted lesson (relation excludes trashed rows).
        if ($lesson === null) {
            return false;
        }

        // Lesson in an archived (soft-deleted) module. Load lesson.module to avoid a query.
        $moduleAlive = $lesson->relationLoaded('module') ? $lesson->module !== null : $lesson->module()->exists();

        if (! $moduleAlive) {
            return false;
        }

        $access = $lessonAccess[$lesson->id] ?? null;

        if ($access === null) {
            return false;
        }

        return $access->unlocked ? true : ($access->unlockAt ?? false);
    }
}
