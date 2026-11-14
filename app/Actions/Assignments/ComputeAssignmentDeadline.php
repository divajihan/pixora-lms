<?php

namespace App\Actions\Assignments;

use App\Actions\Learning\ComputeLessonAccess;
use App\Models\Assignment;
use App\Models\AssignmentDeadlineOverride;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\LessonUnlockOverride;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * THE single place that decides an assignment's deadline.
 *
 * Reference date:
 *   - lesson assignment, drip course: the lesson's effective opening in the
 *     student's batch (batch schedule incl. batch unlock overrides; per-student
 *     unlock overrides do not move deadlines). Drip enrollment without a
 *     batch: enrolled_at as Day 1 (same fallback as ComputeLessonAccess).
 *   - final project, drip course: the batch end date (ends_on).
 *   - non-drip course: enrolled_at.
 * Default deadline: end of day (due_time, app timezone) of reference date +
 * due_offset_days. Override priority: per enrollment > per batch > default.
 */
class ComputeAssignmentDeadline
{
    public function __construct(private readonly ComputeLessonAccess $lessonAccess) {}

    public function for(Assignment $assignment, Enrollment $enrollment): AssignmentDeadline
    {
        return $this->many(collect([[$assignment, $enrollment]]))[self::key($assignment, $enrollment)];
    }

    /**
     * Bulk version without N+1: two queries whatever the number of pairs
     * (lesson unlock overrides + deadline overrides). Load assignment.lesson,
     * enrollment.batch and enrollment.course beforehand.
     *
     * @param  Collection<int, array{0: Assignment, 1: Enrollment}>  $pairs
     * @return array<string, AssignmentDeadline> keyed by key($assignment, $enrollment)
     */
    public function many(Collection $pairs): array
    {
        if ($pairs->isEmpty()) {
            return [];
        }

        $batchIds = $pairs->map(fn (array $pair) => $pair[1]->batch_id)->filter()->unique()->values();
        $lessonIds = $pairs->map(fn (array $pair) => $pair[0]->lesson_id)->filter()->unique()->values();
        $assignmentIds = $pairs->map(fn (array $pair) => $pair[0]->id)->unique()->values();
        $enrollmentIds = $pairs->map(fn (array $pair) => $pair[1]->id)->unique()->values();

        $unlocks = $batchIds->isNotEmpty() && $lessonIds->isNotEmpty()
            ? LessonUnlockOverride::query()
                ->whereIn('batch_id', $batchIds)
                ->whereIn('lesson_id', $lessonIds)
                ->whereNull('enrollment_id')
                ->get()
                ->keyBy(fn (LessonUnlockOverride $override) => $override->batch_id.':'.$override->lesson_id)
            : collect();

        $overrides = AssignmentDeadlineOverride::query()
            ->whereIn('assignment_id', $assignmentIds)
            ->where(fn ($query) => $query
                ->whereIn('enrollment_id', $enrollmentIds)
                ->orWhereIn('batch_id', $batchIds->all()))
            ->get()
            ->keyBy(fn (AssignmentDeadlineOverride $override) => $override->assignment_id.':'.$override->scope_key);

        $result = [];

        foreach ($pairs as [$assignment, $enrollment]) {
            $reference = $this->reference($assignment, $enrollment, $unlocks);
            $default = $this->endOfDueDay($reference, $assignment->due_offset_days);

            $batchOverride = $enrollment->batch_id
                ? $overrides->get($assignment->id.':'.AssignmentDeadlineOverride::scopeKey($enrollment->batch_id, null))
                : null;
            $enrollmentOverride = $overrides->get($assignment->id.':'.AssignmentDeadlineOverride::scopeKey(null, $enrollment->id));

            $batchDue = $batchOverride ? CarbonImmutable::instance($batchOverride->due_at) : null;
            $enrollmentDue = $enrollmentOverride ? CarbonImmutable::instance($enrollmentOverride->due_at) : null;

            $result[self::key($assignment, $enrollment)] = new AssignmentDeadline(
                reference: $reference,
                default: $default,
                batchOverride: $batchDue,
                enrollmentOverride: $enrollmentDue,
                effective: $enrollmentDue ?? $batchDue ?? $default,
                source: match (true) {
                    $enrollmentDue !== null => AssignmentDeadline::SOURCE_ENROLLMENT,
                    $batchDue !== null => AssignmentDeadline::SOURCE_BATCH,
                    default => AssignmentDeadline::SOURCE_DEFAULT,
                },
            );
        }

        return $result;
    }

    /**
     * Batch-level deadline (instructor overview): default, batch override and
     * effective, before any per-student extension. Null for non-drip courses
     * (each student's deadline depends on their enrollment date).
     *
     * @return array{reference: CarbonImmutable, default: CarbonImmutable, override: CarbonImmutable|null, effective: CarbonImmutable}|null
     */
    public function forBatch(Assignment $assignment, ?Batch $batch): ?array
    {
        if ($batch === null) {
            return null;
        }

        if ($assignment->lesson_id !== null) {
            $lesson = $assignment->lesson;
            $reference = $this->lessonAccess->scheduleForBatch($batch, collect([$lesson]))[$lesson->id]['effective'];
        } else {
            $reference = $this->batchEnd($batch);
        }

        $default = $this->endOfDueDay($reference, $assignment->due_offset_days);
        $override = AssignmentDeadlineOverride::query()
            ->where('assignment_id', $assignment->id)
            ->where('scope_key', AssignmentDeadlineOverride::scopeKey($batch->id, null))
            ->value('due_at');
        $override = $override !== null ? CarbonImmutable::parse($override) : null;

        return ['reference' => $reference, 'default' => $default, 'override' => $override, 'effective' => $override ?? $default];
    }

    public static function key(Assignment $assignment, Enrollment $enrollment): string
    {
        return $assignment->id.':'.$enrollment->id;
    }

    /**
     * @param  Collection<string, LessonUnlockOverride>  $unlocks  keyed "batch:lesson"
     */
    private function reference(Assignment $assignment, Enrollment $enrollment, Collection $unlocks): CarbonImmutable
    {
        $enrolledAt = CarbonImmutable::instance($enrollment->enrolled_at)->setTimezone(config('app.timezone'));

        if (! $enrollment->course?->drip_enabled) {
            return $enrolledAt;
        }

        $batch = $enrollment->batch_id ? $enrollment->batch : null;

        if ($assignment->lesson_id === null) {
            return $batch !== null ? $this->batchEnd($batch) : $enrolledAt;
        }

        $lesson = $assignment->lesson;

        if ($batch === null) {
            return $this->lessonAccess->defaultUnlockAt($lesson, null, $enrollment);
        }

        $override = $unlocks->get($batch->id.':'.$lesson->id);

        return $override !== null
            ? CarbonImmutable::instance($override->unlock_at)->setTimezone(config('app.timezone'))
            : $batch->dayStart($lesson->drip_day_offset);
    }

    private function batchEnd(Batch $batch): CarbonImmutable
    {
        return CarbonImmutable::parse($batch->ends_on->format('Y-m-d'), config('app.timezone'));
    }

    /**
     * End of the due day: (reference date + N days) at due_time:59 (app timezone).
     */
    private function endOfDueDay(CarbonInterface $reference, int $offsetDays): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) config('pixora.assignments.due_time', '23:59')) + [1 => 59]);

        return CarbonImmutable::instance($reference)
            ->setTimezone(config('app.timezone'))
            ->startOfDay()
            ->addDays($offsetDays)
            ->setTime($hour, $minute, 59);
    }
}
