<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EnrollmentPolicy
{
    /**
     * Learning area: only the owner, while the enrollment is active and not past expires_at.
     * (Per-lesson access/drip arrives in Phase 4.)
     */
    public function learn(User $user, Enrollment $enrollment): Response
    {
        if ($enrollment->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        if ($enrollment->grantsAccess()) {
            return Response::allow();
        }

        return Response::deny(match ($enrollment->status) {
            EnrollmentStatus::Revoked => 'Akses Anda ke kursus ini telah dicabut.',
            default => 'Masa akses Anda ke kursus ini telah berakhir.',
        });
    }
}
