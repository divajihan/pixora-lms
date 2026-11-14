<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
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
 * expires_at = enrolled_at + course.access_duration_days, or null (lifetime).
 */
class GrantEnrollment
{
    public function handle(User $user, Course $course, ?Order $order = null): Enrollment
    {
        $now = now();
        $attributes = [
            'order_id' => $order?->id,
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => $now,
            'expires_at' => $this->expiresAt($course, $now),
        ];

        $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->lockForUpdate()->first();

        if ($enrollment === null) {
            try {
                return Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, ...$attributes]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request created it first.
                $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->lockForUpdate()->firstOrFail();
            }
        }

        if (in_array($enrollment->status, [EnrollmentStatus::Expired, EnrollmentStatus::Revoked], true)
            || ($enrollment->status === EnrollmentStatus::Active && ! $enrollment->grantsAccess())) {
            $enrollment->update($attributes);
        }

        return $enrollment;
    }

    private function expiresAt(Course $course, CarbonInterface $from): ?CarbonInterface
    {
        return $course->access_duration_days ? $from->copy()->addDays($course->access_duration_days) : null;
    }
}
