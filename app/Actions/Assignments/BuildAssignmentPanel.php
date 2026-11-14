<?php

namespace App\Actions\Assignments;

use App\Http\Resources\AttachmentResource;
use App\Http\Resources\SubmissionCommentResource;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Rules\AllowedImage;

/**
 * Props for the student's assignment block (lesson page / final project page):
 * instructions, effective deadline, own submission with images and the
 * feedback thread, and what the UI may offer (submit / edit).
 *
 * Opening it marks the feedback as seen (student_seen_feedback_at).
 */
class BuildAssignmentPanel
{
    public function __construct(private readonly ComputeAssignmentDeadline $deadlines) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Enrollment $enrollment, Assignment $assignment): array
    {
        $assignment->loadMissing('lesson');
        $deadline = $this->deadlines->for($assignment, $enrollment);

        $submission = Submission::query()
            ->where('assignment_id', $assignment->id)
            ->where('enrollment_id', $enrollment->id)
            ->with(['attachments', 'comments.author:id,name', 'comments.attachments'])
            ->first();

        $hasComments = $submission !== null && $submission->comments->isNotEmpty();
        $hasUnseen = $hasComments && $submission->comments->contains(
            fn ($comment) => $submission->student_seen_feedback_at === null
                || $comment->updated_at?->gt($submission->student_seen_feedback_at),
        );

        if ($hasUnseen) {
            $submission->forceFill(['student_seen_feedback_at' => now()])->saveQuietly();
        }

        $state = SubmissionRules::state($submission, $deadline, $hasComments);
        $editBlocker = $submission ? SubmissionRules::editBlocker($submission, $deadline, $hasComments) : null;

        return [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions_html' => $assignment->instructions_html,
                'is_final_project' => $assignment->is_final_project,
                'max_images' => $assignment->imageLimit(),
            ],
            'deadline' => [
                'due_at' => $deadline->effective->toIso8601String(),
                'is_extended' => $deadline->source !== AssignmentDeadline::SOURCE_DEFAULT,
                'has_passed' => $deadline->hasPassed(),
            ],
            'state' => $state,
            'state_label' => SubmissionRules::label($state),
            'submission' => $submission ? [
                'id' => $submission->id,
                'body' => $submission->body,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'is_late' => $deadline->isLate($submission->submitted_at),
                'is_reviewed' => $submission->status->value === 'reviewed',
                'can_edit' => $editBlocker === null,
                'edit_blocker' => $editBlocker,
                'images' => AttachmentResource::collection($submission->attachments)->resolve(),
                'comments' => SubmissionCommentResource::collection($submission->comments)->resolve(),
                'had_unseen_feedback' => $hasUnseen,
            ] : null,
            'limits' => [
                'max_images' => $assignment->imageLimit(),
                'max_image_kb' => (int) config('pixora.assignments.max_image_kb', 10240),
                'max_image_label' => AllowedImage::humanSize((int) config('pixora.assignments.max_image_kb', 10240)),
                'accept' => implode(',', AllowedImage::MIMES),
            ],
        ];
    }
}
