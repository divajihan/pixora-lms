<?php

namespace App\Policies;

use App\Actions\Learning\ComputeLessonAccess;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EnrollmentPolicy
{
    public function __construct(private readonly ComputeLessonAccess $access) {}

    /**
     * Learning area: only the owner, while the enrollment grants access
     * (active/completed, not expired) and the course is not a draft.
     * Per-lesson rules: LessonPolicy::viewContent.
     */
    public function learn(User $user, Enrollment $enrollment): Response
    {
        if ($enrollment->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        if ($this->access->canAccessCourse($enrollment)) {
            return Response::allow();
        }

        return Response::deny(match (true) {
            $enrollment->status === EnrollmentStatus::Revoked => 'Akses Anda ke kursus ini telah dicabut.',
            $enrollment->course?->status === CourseStatus::Draft => 'Kursus ini sedang tidak tersedia.',
            default => 'Masa akses Anda ke kursus ini telah berakhir.',
        });
    }
}
