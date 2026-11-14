<?php

namespace App\Actions\Assignments;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replace/add/remove images or change the note — only while the effective
 * deadline has not passed, the submission is not reviewed and has no
 * feedback (SubmissionRules::editBlocker, re-checked under a row lock).
 * Removed files are deleted by a job after commit.
 */
class UpdateSubmission
{
    public function __construct(
        private readonly ComputeAssignmentDeadline $deadlines,
        private readonly StoreImages $images,
    ) {}

    /**
     * @param  list<UploadedFile>  $newFiles
     * @param  list<int>  $removeIds  attachment ids of this submission
     */
    public function handle(Submission $submission, array $newFiles, array $removeIds, ?string $body, User $student): Submission
    {
        $this->guardEditable($submission);

        $assignment = $submission->assignment;
        $existing = $submission->attachments()->get();
        $removing = $existing->whereIn('id', $removeIds);
        $remaining = $existing->count() - $removing->count() + count($newFiles);
        $limit = $assignment->imageLimit();

        if ($remaining < 1 || $remaining > $limit) {
            throw ValidationException::withMessages(['images' => "Pengumpulan harus berisi 1 sampai {$limit} gambar."]);
        }

        $directory = "submissions/{$assignment->id}/{$submission->enrollment_id}";

        return $this->images->store($newFiles, $directory, 'images', $student, fn (array $rows) => DB::transaction(function () use ($submission, $removing, $rows, $body): Submission {
            $locked = Submission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->guardEditable($locked);

            $this->images->delete($removing);
            StoreImages::attach($locked->attachments(), $rows);
            $locked->update(['body' => $body]);

            return $locked;
        }));
    }

    private function guardEditable(Submission $submission): void
    {
        $submission->loadMissing(['assignment.lesson', 'enrollment.batch', 'enrollment.course']);
        $deadline = $this->deadlines->for($submission->assignment, $submission->enrollment);
        $blocker = SubmissionRules::editBlocker($submission, $deadline, $submission->comments()->exists());

        if ($blocker !== null) {
            throw ValidationException::withMessages(['images' => $blocker]);
        }
    }
}
