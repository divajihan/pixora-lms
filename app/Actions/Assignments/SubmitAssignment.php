<?php

namespace App\Actions\Assignments;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A student's first (and only) submission. Allowed any time the assignment
 * is available — after the deadline it is simply late (and final).
 */
class SubmitAssignment
{
    public function __construct(
        private readonly AssignmentAvailability $availability,
        private readonly StoreImages $images,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(Enrollment $enrollment, Assignment $assignment, array $files, ?string $body, User $student): Submission
    {
        if (! $this->availability->isAvailable($enrollment, $assignment)) {
            throw ValidationException::withMessages(['images' => 'Tugas ini belum terbuka untuk Anda.']);
        }

        if (Submission::where('assignment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->exists()) {
            throw ValidationException::withMessages(['images' => 'Anda sudah mengumpulkan tugas ini.']);
        }

        $limit = $assignment->imageLimit();

        if ($files === [] || count($files) > $limit) {
            throw ValidationException::withMessages(['images' => "Unggah 1 sampai {$limit} gambar."]);
        }

        $directory = "submissions/{$assignment->id}/{$enrollment->id}";

        try {
            return $this->images->store($files, $directory, 'images', $student, fn (array $rows) => DB::transaction(function () use ($assignment, $enrollment, $body, $rows): Submission {
                $submission = Submission::create([
                    'assignment_id' => $assignment->id,
                    'enrollment_id' => $enrollment->id,
                    'body' => $body,
                    'status' => SubmissionStatus::Submitted,
                    'submitted_at' => now(),
                ]);

                StoreImages::attach($submission->attachments(), $rows);

                return $submission;
            }));
        } catch (UniqueConstraintViolationException) {
            // A double click / second tab created it first; its files were removed.
            throw ValidationException::withMessages(['images' => 'Anda sudah mengumpulkan tugas ini.']);
        }
    }
}
