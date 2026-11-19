<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * - claim: only the owner of the enrollment (others 404).
 * - download: the owner (active certificate only), staff/admin
 *   (certificates.view / certificates.manage) and instructors of the course.
 * - viewAny: the admin list (staff + admin).
 * - manage: revoke, manual issue, regenerate PDF (certificates.manage = admin).
 */
class CertificatePolicy
{
    public function claim(User $user, Enrollment $enrollment): Response
    {
        return $enrollment->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function download(User $user, Certificate $certificate): Response
    {
        if ($user->can(Permission::CertificatesView->value) || $user->can(Permission::CertificatesManage->value)) {
            return Response::allow();
        }

        if ($certificate->user_id === $user->id) {
            return $certificate->isRevoked() ? Response::denyAsNotFound() : Response::allow();
        }

        return AssignmentPolicy::isCourseInstructor($user, $certificate->course)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CertificatesView->value) || $user->can(Permission::CertificatesManage->value);
    }

    public function manage(User $user): bool
    {
        return $user->can(Permission::CertificatesManage->value);
    }
}
