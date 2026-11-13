<?php

namespace App\Policies;

use App\Enums\Permission;
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
     */
    public function view(?User $user, Course $course): Response
    {
        return $course->isPublished()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Admin course list and editing (courses.manage). Staff do not have it.
     */
    public function manage(User $user): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->manage($user);
    }

    /**
     * Read-only curriculum: content managers, or instructors assigned to this
     * course. Other courses are hidden (404) from instructors.
     */
    public function viewCurriculum(User $user, Course $course): Response
    {
        if ($this->manage($user)) {
            return Response::allow();
        }

        return $user->can(Permission::CoursesTeach->value) && $course->hasInstructor($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Instructor area: list of courses assigned to the user.
     */
    public function viewAssigned(User $user): bool
    {
        return $user->can(Permission::CoursesTeach->value);
    }
}
