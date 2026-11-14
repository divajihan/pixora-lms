<?php

namespace App\Actions\Assignments;

use App\Enums\CommentAuthorRole;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Feedback: text and/or images from an instructor of the course, staff or an
 * admin (authorised by SubmissionPolicy::comment).
 *
 * - Only after the effective deadline, and never on a late submission
 *   (SubmissionRules::commentBlocker, also re-checked under a row lock).
 * - The first comment by an instructor of the course marks the submission
 *   reviewed (reviewed_at/by) and locks it for the student.
 */
class PostSubmissionComment
{
    public function __construct(
        private readonly ComputeAssignmentDeadline $deadlines,
        private readonly StoreImages $images,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(Submission $submission, User $author, ?string $body, array $files): SubmissionComment
    {
        $this->guard($submission);

        $body = $body !== null && trim($body) !== '' ? trim($body) : null;
        $limit = (int) config('pixora.assignments.comment_max_images', 5);

        if ($body === null && $files === []) {
            throw ValidationException::withMessages(['body' => 'Tulis komentar atau lampirkan gambar.']);
        }

        if (count($files) > $limit) {
            throw ValidationException::withMessages(['images' => "Maksimal {$limit} gambar per komentar."]);
        }

        $role = self::authorRole($author, $submission);

        return $this->images->store($files, "submission-comments/{$submission->id}", 'images', $author, fn (array $rows) => DB::transaction(function () use ($submission, $author, $body, $rows, $role): SubmissionComment {
            $locked = Submission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->guard($locked);

            $comment = $locked->comments()->create([
                'user_id' => $author->id,
                'author_role' => $role,
                'body' => $body,
            ]);
            StoreImages::attach($comment->attachments(), $rows);

            if ($role === CommentAuthorRole::Instructor && $locked->status === SubmissionStatus::Submitted) {
                $locked->update(['status' => SubmissionStatus::Reviewed, 'reviewed_at' => now(), 'reviewed_by' => $author->id]);
            }

            return $comment;
        }));
    }

    /**
     * Snapshot shown to the student: instructor of THIS course first, then admin, else staff.
     */
    public static function authorRole(User $author, Submission $submission): CommentAuthorRole
    {
        $course = $submission->assignment->course;

        return match (true) {
            $course !== null && $course->hasInstructor($author) => CommentAuthorRole::Instructor,
            $author->hasRole(Role::Admin->value) => CommentAuthorRole::Admin,
            default => CommentAuthorRole::Staff,
        };
    }

    private function guard(Submission $submission): void
    {
        $submission->loadMissing(['assignment.lesson', 'assignment.course', 'enrollment.batch', 'enrollment.course']);
        $deadline = $this->deadlines->for($submission->assignment, $submission->enrollment);
        $blocker = SubmissionRules::commentBlocker($submission, $deadline);

        if ($blocker !== null) {
            throw ValidationException::withMessages(['body' => $blocker]);
        }
    }
}
