<?php

namespace App\Actions\Enrollments;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Free courses: enroll immediately, without an order (order_id = null).
 * Paid courses are rejected — they must go through checkout.
 */
class EnrollInFreeCourse
{
    public function __construct(private readonly GrantEnrollment $grant) {}

    public function handle(User $user, Course $course): Enrollment
    {
        if (! $course->isPublished()) {
            throw ValidationException::withMessages(['course' => 'Kursus ini belum tersedia.']);
        }

        if (! $course->isFree()) {
            throw ValidationException::withMessages(['course' => 'Kursus ini berbayar. Silakan beli melalui halaman checkout.']);
        }

        return DB::transaction(fn () => $this->grant->handle($user, $course));
    }
}
