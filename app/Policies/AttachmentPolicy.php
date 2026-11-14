<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\SubmissionComment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A file is visible to whoever may view its submission (SubmissionPolicy).
 * Images of a deleted comment are no longer shown to the student.
 */
class AttachmentPolicy
{
    public function __construct(private readonly SubmissionPolicy $submissions) {}

    public function view(User $user, Attachment $attachment): Response
    {
        $submission = $attachment->owningSubmission();

        if ($submission === null) {
            return Response::denyAsNotFound();
        }

        if ($this->submissions->isReviewer($user, $submission)) {
            return Response::allow();
        }

        if (! $this->submissions->isOwner($user, $submission)) {
            return Response::denyAsNotFound();
        }

        $deletedComment = $attachment->attachable_type === 'submission_comment'
            && SubmissionComment::onlyTrashed()->whereKey($attachment->attachable_id)->exists();

        return $deletedComment ? Response::denyAsNotFound() : Response::allow();
    }
}
