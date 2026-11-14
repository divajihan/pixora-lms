<?php

namespace App\Actions\Enrollments;

use App\Models\Batch;
use App\Models\Course;
use Illuminate\Validation\ValidationException;

/**
 * Validates the batch chosen at checkout / free enrollment.
 * - Drip courses: a batch of this course that is open for registration
 *   (published, not finished, registration not closed, not full) is required.
 * - Non-drip courses: no batch (any given id is ignored).
 */
class ResolveEnrollmentBatch
{
    public function handle(Course $course, ?int $batchId): ?Batch
    {
        if (! $course->drip_enabled) {
            return null;
        }

        if ($batchId === null) {
            throw ValidationException::withMessages(['batch_id' => 'Pilih batch (angkatan) yang ingin Anda ikuti.']);
        }

        /** @var Batch|null $batch */
        $batch = Batch::query()->where('course_id', $course->id)->whereKey($batchId)->lockForUpdate()->first();

        if ($batch === null) {
            throw ValidationException::withMessages(['batch_id' => 'Batch tidak ditemukan untuk kursus ini.']);
        }

        $blocker = $batch->registrationBlocker();

        if ($blocker !== null) {
            throw ValidationException::withMessages(['batch_id' => $blocker]);
        }

        return $batch;
    }
}
