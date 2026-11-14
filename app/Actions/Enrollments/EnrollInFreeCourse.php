<?php

namespace App\Actions\Enrollments;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Free courses: enroll immediately, without an order (order_id = null).
 * Paid courses are rejected — they must go through checkout. Drip courses
 * need an open batch (ResolveEnrollmentBatch).
 */
class EnrollInFreeCourse
{
    public function __construct(
        private readonly GrantEnrollment $grant,
        private readonly ResolveEnrollmentBatch $resolveBatch,
    ) {}

    public function handle(User $user, Course $course, ?int $batchId = null): Enrollment
    {
        if (! $course->isPublished()) {
            throw ValidationException::withMessages(['course' => 'Kursus ini belum tersedia.']);
        }

        if (! $course->isFree()) {
            throw ValidationException::withMessages(['course' => 'Kursus ini berbayar. Silakan beli melalui halaman checkout.']);
        }

        return DB::transaction(function () use ($user, $course, $batchId): Enrollment {
            if ($user->enrollments()->where('course_id', $course->id)->accessible()->exists()) {
                return $user->enrollments()->where('course_id', $course->id)->firstOrFail();
            }

            return $this->grant->handle($user, $course, null, $this->resolveBatch->handle($course, $batchId));
        });
    }
}
