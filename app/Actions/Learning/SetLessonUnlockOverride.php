<?php

namespace App\Actions\Learning;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonUnlockOverride;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Instructor/admin changes the unlock date of a lesson for a whole batch
 * (enrollment null) or one student, or resets it to the default schedule.
 *
 * RULE: a lesson that is already open (effective unlock_at <= now) can never
 * be moved into the future again — opened lessons stay open. Lessons that are
 * not open yet may be opened earlier or moved to another date.
 */
class SetLessonUnlockOverride
{
    public function __construct(private readonly ComputeLessonAccess $access) {}

    public function set(Lesson $lesson, Batch $batch, ?Enrollment $enrollment, CarbonInterface $unlockAt, User $actor): LessonUnlockOverride
    {
        $this->guardScope($lesson, $batch, $enrollment);
        $unlockAt = CarbonImmutable::instance($unlockAt);

        return DB::transaction(function () use ($lesson, $batch, $enrollment, $unlockAt, $actor): LessonUnlockOverride {
            $this->lockScope($lesson, $batch);
            $current = $this->effective($lesson, $batch, $enrollment);

            if ($current->lte(now()) && $unlockAt->isFuture()) {
                throw ValidationException::withMessages([
                    'unlock_at' => "Lesson ini sudah terbuka sejak {$this->format($current)} dan tidak bisa dikunci kembali. Lesson yang sudah terbuka tetap terbuka.",
                ]);
            }

            return LessonUnlockOverride::updateOrCreate(
                ['lesson_id' => $lesson->id, 'batch_id' => $batch->id, 'scope_key' => $enrollment->id ?? 0],
                ['enrollment_id' => $enrollment?->id, 'unlock_at' => $unlockAt, 'created_by' => $actor->id],
            );
        });
    }

    /**
     * Removes the override (back to the batch/default schedule), unless that
     * would lock an already-open lesson again.
     */
    public function reset(Lesson $lesson, Batch $batch, ?Enrollment $enrollment): void
    {
        $this->guardScope($lesson, $batch, $enrollment);

        DB::transaction(function () use ($lesson, $batch, $enrollment): void {
            $this->lockScope($lesson, $batch);

            $override = LessonUnlockOverride::where('lesson_id', $lesson->id)
                ->where('batch_id', $batch->id)
                ->where('scope_key', $enrollment->id ?? 0)
                ->first();

            if ($override === null) {
                return;
            }

            $current = $this->effective($lesson, $batch, $enrollment);
            $override->delete();
            $after = $this->effective($lesson, $batch, $enrollment);

            if ($current->lte(now()) && $after->isFuture()) {
                throw ValidationException::withMessages([
                    'unlock_at' => "Jadwal default membuka lesson ini pada {$this->format($after)}, padahal lesson sudah terbuka. Lesson yang sudah terbuka tidak bisa dikunci kembali.",
                ]);
            }
        });
    }

    /**
     * Effective unlock for the scope: per student (incl. their override) or the batch.
     */
    private function effective(Lesson $lesson, Batch $batch, ?Enrollment $enrollment): CarbonImmutable
    {
        if ($enrollment !== null) {
            return $this->access->access($enrollment->fresh() ?? $enrollment, $lesson)->unlockAt ?? CarbonImmutable::now()->subSecond();
        }

        return $this->access->scheduleForBatch($batch, collect([$lesson]))[$lesson->id]['effective'];
    }

    private function guardScope(Lesson $lesson, Batch $batch, ?Enrollment $enrollment): void
    {
        $valid = $lesson->course_id === $batch->course_id
            && $batch->course?->drip_enabled
            && ($enrollment === null || $enrollment->batch_id === $batch->id);

        if (! $valid) {
            throw ValidationException::withMessages(['unlock_at' => 'Lesson, batch, dan siswa tidak cocok.']);
        }
    }

    /**
     * Serialises concurrent schedule edits for the same lesson/batch.
     */
    private function lockScope(Lesson $lesson, Batch $batch): void
    {
        Batch::whereKey($batch->id)->lockForUpdate()->first();
        Lesson::whereKey($lesson->id)->lockForUpdate()->first();
    }

    private function format(CarbonInterface $date): string
    {
        return $date->setTimezone(config('app.timezone'))->translatedFormat('j F Y H:i');
    }
}
