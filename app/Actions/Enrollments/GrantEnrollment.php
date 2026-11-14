<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Gives a user access to a course (paid order or free course). Idempotent and
 * race-safe: unique (user_id, course_id) + "create, or fetch on conflict".
 *
 * - No enrollment yet → create (active, enrolled_at = now).
 * - Already active/completed → unchanged (never shortened).
 * - Expired/revoked (e.g. buying again) → reactivated with a fresh period.
 *
 * expires_at (null = lifetime when access_duration_days is null):
 *   - drip course: end of batch.ends_on + access_duration_days days;
 *   - other courses: enrolled_at + access_duration_days days.
 * batch_id comes from the order item / free-enrollment choice (drip courses).
 */
class GrantEnrollment
{
    public function handle(User $user, Course $course, ?Order $order = null, ?Batch $batch = null): Enrollment
    {
        $now = now();
        $attributes = [
            'order_id' => $order?->id,
            'batch_id' => $batch?->id,
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => $now,
            'expires_at' => $this->expiresAt($course, $now, $batch),
        ];

        $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->lockForUpdate()->first();

        if ($enrollment === null) {
            try {
                $enrollment = Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, ...$attributes]);
                $this->warnIfOverCapacity($batch);

                return $enrollment;
            } catch (UniqueConstraintViolationException) {
                // A concurrent request created it first.
                $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->lockForUpdate()->firstOrFail();
            }
        }

        if (in_array($enrollment->status, [EnrollmentStatus::Expired, EnrollmentStatus::Revoked], true)
            || ($enrollment->status === EnrollmentStatus::Active && ! $enrollment->grantsAccess())) {
            $enrollment->update($attributes);
            $this->warnIfOverCapacity($batch);
        }

        return $enrollment;
    }

    private function expiresAt(Course $course, CarbonInterface $from, ?Batch $batch): ?CarbonInterface
    {
        if (! $course->access_duration_days) {
            return null;
        }

        $start = $batch !== null ? $batch->ends_on->copy()->endOfDay() : $from;

        return $start->copy()->addDays($course->access_duration_days);
    }

    /**
     * Successful payments are never refused for a full batch; the overflow is
     * logged and shown to admins on the batch page instead.
     */
    private function warnIfOverCapacity(?Batch $batch): void
    {
        if ($batch !== null && $batch->capacity !== null && $batch->enrollments()->whereIn('status', Batch::SEAT_STATUSES)->count() > $batch->capacity) {
            logger()->warning('Batch over capacity after enrollment', ['batch_id' => $batch->id, 'capacity' => $batch->capacity]);
        }
    }
}
