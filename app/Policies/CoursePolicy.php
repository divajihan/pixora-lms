<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CoursePolicy
{
    /**
     * Anyone (including guests) may browse the public catalog.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Public course page: only published courses. Anything else is reported
     * as 404 so drafts/archived courses are not discoverable.
     * Previews for admins/instructors arrive with course management (Phase 2).
     */
    public function view(?User $user, Course $course): Response
    {
        return $course->isPublished()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
