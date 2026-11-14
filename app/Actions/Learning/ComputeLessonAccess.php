<?php

namespace App\Actions\Learning;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonUnlockOverride;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * THE single place that decides when a lesson opens and whether a student may
 * see it. Used by LessonPolicy, the player, progress and instructor schedules.
 *
 * Unlock time (drip courses), highest priority first:
 *   1. override for this enrollment (lesson_unlock_overrides.enrollment_id = X)
 *   2. override for the whole batch (enrollment_id NULL)
 *   3. default: batch.starts_on + drip_day_offset days at LESSON_UNLOCK_TIME
 * Non-drip courses and preview lessons are open immediately.
 *
 * A lesson is viewable when the enrollment is active/completed and not expired,
 * the course is not a draft (archived is fine for enrolled students), the
 * lesson is published in a live module, and its unlock time has passed.
 */
class ComputeLessonAccess
{
    public function canAccessCourse(Enrollment $enrollment): bool
    {
        $course = $enrollment->course;

        return in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true)
            && ($enrollment->expires_at === null || $enrollment->expires_at->isFuture())
            && $course instanceof Course
            && ! $course->trashed()
            && $course->status !== CourseStatus::Draft;
    }

    public function unlockAt(Enrollment $enrollment, Lesson $lesson): ?CarbonImmutable
    {
        return $this->access($enrollment, $lesson)->unlockAt;
    }

    public function isUnlocked(Enrollment $enrollment, Lesson $lesson, ?CarbonInterface $now = null): bool
    {
        $unlockAt = $this->unlockAt($enrollment, $lesson);

        return $unlockAt === null || $unlockAt->lte($now ?? now());
    }

    public function access(Enrollment $enrollment, Lesson $lesson): LessonAccess
    {
        return $this->forEnrollment($enrollment, collect([$lesson]))[$lesson->id];
    }

    /**
     * Full check for opening a lesson's content.
     */
    public function canViewLesson(Enrollment $enrollment, Lesson $lesson): bool
    {
        return $this->canAccessCourse($enrollment)
            && $lesson->course_id === $enrollment->course_id
            && $lesson->status === LessonStatus::Published
            && $lesson->module()->exists()
            && $this->access($enrollment, $lesson)->unlocked;
    }

    /**
     * Bulk version for one enrollment: overrides are loaded in ONE query.
     *
     * @param  Collection<int, Lesson>  $lessons
     * @return array<int, LessonAccess> keyed by lesson id
     */
    public function forEnrollment(Enrollment $enrollment, Collection $lessons, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $course = $enrollment->course;
        $batch = $enrollment->batch_id ? $enrollment->batch : null;

        $overrides = $batch !== null && $lessons->isNotEmpty()
            ? LessonUnlockOverride::query()
                ->where('batch_id', $batch->id)
                ->whereIn('lesson_id', $lessons->pluck('id')->all())
                ->where(fn ($query) => $query->whereNull('enrollment_id')->orWhere('enrollment_id', $enrollment->id))
                ->get()
            : collect();

        $byEnrollment = $overrides->whereNotNull('enrollment_id')->keyBy('lesson_id');
        $byBatch = $overrides->whereNull('enrollment_id')->keyBy('lesson_id');

        $result = [];

        foreach ($lessons as $lesson) {
            if ($lesson->is_preview) {
                $result[$lesson->id] = new LessonAccess(true, null, LessonAccess::SOURCE_PREVIEW);

                continue;
            }

            if (! $course?->drip_enabled) {
                $result[$lesson->id] = new LessonAccess(true, null, LessonAccess::SOURCE_IMMEDIATE);

                continue;
            }

            [$unlockAt, $source] = match (true) {
                $byEnrollment->has($lesson->id) => [CarbonImmutable::instance($byEnrollment[$lesson->id]->unlock_at), LessonAccess::SOURCE_ENROLLMENT],
                $byBatch->has($lesson->id) => [CarbonImmutable::instance($byBatch[$lesson->id]->unlock_at), LessonAccess::SOURCE_BATCH],
                default => [$this->defaultUnlockAt($lesson, $batch, $enrollment), LessonAccess::SOURCE_DEFAULT],
            };

            $result[$lesson->id] = new LessonAccess($unlockAt->lte($now), $unlockAt, $source);
        }

        return $result;
    }

    /**
     * Default schedule: batch start + offset days. Enrollments of a drip course
     * without a batch (created before batches existed) fall back to their
     * enrollment date as Day 1.
     */
    public function defaultUnlockAt(Lesson $lesson, ?Batch $batch, ?Enrollment $enrollment = null): CarbonImmutable
    {
        if ($batch !== null) {
            return $batch->dayStart($lesson->drip_day_offset);
        }

        $start = CarbonImmutable::instance($enrollment->enrolled_at ?? now())->setTimezone(config('app.timezone'));

        return (new Batch(['starts_on' => $start->toDateString()]))->dayStart($lesson->drip_day_offset);
    }

    /**
     * Batch-level schedule for instructors: default, batch override and the
     * effective date (before any per-student override).
     *
     * @param  Collection<int, Lesson>  $lessons
     * @return array<int, array{default: CarbonImmutable, override: CarbonImmutable|null, effective: CarbonImmutable}>
     */
    public function scheduleForBatch(Batch $batch, Collection $lessons): array
    {
        $overrides = LessonUnlockOverride::query()
            ->where('batch_id', $batch->id)
            ->whereNull('enrollment_id')
            ->whereIn('lesson_id', $lessons->pluck('id')->all())
            ->get()
            ->keyBy('lesson_id');

        $schedule = [];

        foreach ($lessons as $lesson) {
            $default = $batch->dayStart($lesson->drip_day_offset);
            $override = $overrides->has($lesson->id) ? CarbonImmutable::instance($overrides[$lesson->id]->unlock_at) : null;
            $schedule[$lesson->id] = ['default' => $default, 'override' => $override, 'effective' => $override ?? $default];
        }

        return $schedule;
    }
}
