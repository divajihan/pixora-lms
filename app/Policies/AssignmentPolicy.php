<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * - manage: create/edit assignments in the curriculum (courses.manage = admin).
 * - review: see submissions of a course — instructors assigned to the course
 *   (assignments.review) and staff/admin (assignments.comment, all courses).
 * - manageDeadlines: extensions — instructors of the course and admins, not staff.
 * Not visible = 404.
 */
class AssignmentPolicy
{
    public function manage(User $user): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->manage($user);
    }

    public function review(User $user, Assignment $assignment): Response
    {
        return self::canReviewCourse($user, $assignment->course) ? Response::allow() : Response::denyAsNotFound();
    }

    public function manageDeadlines(User $user, Assignment $assignment): Response
    {
        if ($user->can(Permission::CoursesManage->value)) {
            return Response::allow();
        }

        return self::isCourseInstructor($user, $assignment->course) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Staff/admin see every course; instructors only the ones assigned to them.
     */
    public static function canReviewCourse(User $user, ?Course $course): bool
    {
        return $course !== null
            && ($user->can(Permission::AssignmentsComment->value) || self::isCourseInstructor($user, $course));
    }

    public static function isCourseInstructor(User $user, ?Course $course): bool
    {
        return $course !== null
            && $user->can(Permission::AssignmentsReview->value)
            && $course->hasInstructor($user);
    }
}
