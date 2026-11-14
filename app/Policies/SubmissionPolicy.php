<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who sees a submission (its images and comment thread):
 *   - the student who owns it (through the enrollment);
 *   - instructors assigned to the course;
 *   - staff and admins (assignments.comment).
 * Everyone else gets 404. Timing rules (deadline, late) are enforced by the
 * actions (SubmissionRules), not here.
 */
class SubmissionPolicy
{
    public function view(User $user, Submission $submission): Response
    {
        return $this->isOwner($user, $submission) || $this->isReviewer($user, $submission)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Student edits their own submission (rules in UpdateSubmission).
     */
    public function update(User $user, Submission $submission): Response
    {
        return $this->isOwner($user, $submission) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Feedback: instructor of the course, staff, admin. Students cannot reply.
     */
    public function comment(User $user, Submission $submission): Response
    {
        if ($this->isReviewer($user, $submission)) {
            return Response::allow();
        }

        return $this->isOwner($user, $submission)
            ? Response::deny('Siswa tidak dapat membalas komentar.')
            : Response::denyAsNotFound();
    }

    public function isOwner(User $user, Submission $submission): bool
    {
        return $submission->enrollment?->user_id === $user->id;
    }

    public function isReviewer(User $user, Submission $submission): bool
    {
        return AssignmentPolicy::canReviewCourse($user, $submission->assignment?->course);
    }
}
