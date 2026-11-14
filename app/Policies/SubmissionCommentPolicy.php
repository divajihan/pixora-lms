<?php

namespace App\Policies;

use App\Models\SubmissionComment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Authors edit/delete only their own comments, and must still be reviewers of
 * the course (instructor of the course, staff, admin). Others: 404.
 */
class SubmissionCommentPolicy
{
    public function __construct(private readonly SubmissionPolicy $submissions) {}

    public function update(User $user, SubmissionComment $comment): Response
    {
        return $comment->user_id === $user->id && $this->submissions->isReviewer($user, $comment->submission)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, SubmissionComment $comment): Response
    {
        return $this->update($user, $comment);
    }
}
