<?php

namespace App\Actions\Assignments;

use App\Models\SubmissionComment;
use Illuminate\Validation\ValidationException;

/**
 * The author edits the text of their comment (images stay). Marked "diedit"
 * (edited_at). A comment must keep text or at least one image.
 */
class EditSubmissionComment
{
    public function handle(SubmissionComment $comment, ?string $body): SubmissionComment
    {
        $body = $body !== null && trim($body) !== '' ? trim($body) : null;

        if ($body === null && ! $comment->attachments()->exists()) {
            throw ValidationException::withMessages(['body' => 'Komentar tidak boleh kosong.']);
        }

        if ($body !== $comment->body) {
            $comment->update(['body' => $body, 'edited_at' => now()]);
        }

        return $comment;
    }
}
