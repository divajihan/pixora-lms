<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Models\AssignmentDeadlineOverride;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Deadline extensions for a batch or one student (instructors of the course
 * and admins; authorised by AssignmentPolicy::manageDeadlines).
 *
 * - A new deadline must be in the future.
 * - Exactly one scope: enrollment (wins) or batch.
 * - reset() removes the override: back to the batch/default deadline.
 * Late status is computed, so an extension immediately turns a late
 * submission into an on-time one.
 */
class SetDeadlineOverride
{
    public function set(Assignment $assignment, ?Batch $batch, ?Enrollment $enrollment, CarbonInterface $dueAt, User $actor): AssignmentDeadlineOverride
    {
        $this->guardScope($assignment, $batch, $enrollment);
        $dueAt = CarbonImmutable::instance($dueAt);

        if (! $dueAt->isFuture()) {
            throw ValidationException::withMessages(['due_at' => 'Tenggat baru harus di masa depan.']);
        }

        return AssignmentDeadlineOverride::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'scope_key' => AssignmentDeadlineOverride::scopeKey($enrollment ? null : $batch?->id, $enrollment?->id),
            ],
            [
                'batch_id' => $enrollment ? null : $batch?->id,
                'enrollment_id' => $enrollment?->id,
                'due_at' => $dueAt,
                'created_by' => $actor->id,
            ],
        );
    }

    public function reset(Assignment $assignment, ?Batch $batch, ?Enrollment $enrollment): void
    {
        $this->guardScope($assignment, $batch, $enrollment);

        AssignmentDeadlineOverride::query()
            ->where('assignment_id', $assignment->id)
            ->where('scope_key', AssignmentDeadlineOverride::scopeKey($enrollment ? null : $batch?->id, $enrollment?->id))
            ->delete();
    }

    private function guardScope(Assignment $assignment, ?Batch $batch, ?Enrollment $enrollment): void
    {
        $valid = match (true) {
            $enrollment !== null => $enrollment->course_id === $assignment->course_id,
            $batch !== null => $batch->course_id === $assignment->course_id,
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['due_at' => 'Pilih batch atau siswa dari kursus tugas ini.']);
        }
    }
}
