<?php

namespace App\Actions\Assignments;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use Carbon\CarbonInterface;

/**
 * Business rules on a submission given its effective deadline. Shared by the
 * actions (server enforcement) and the props (what the UI offers).
 */
class SubmissionRules
{
    public const STATE_NOT_SUBMITTED = 'not_submitted';

    public const STATE_ON_TIME = 'on_time';

    public const STATE_LATE = 'late';

    public const STATE_FEEDBACK = 'feedback';

    /**
     * Student-facing state: Belum dikumpulkan / Tepat waktu / Terlambat /
     * Sudah ada feedback (any visible comment).
     */
    public static function state(?Submission $submission, AssignmentDeadline $deadline, bool $hasComments): string
    {
        return match (true) {
            $submission === null => self::STATE_NOT_SUBMITTED,
            $hasComments => self::STATE_FEEDBACK,
            $deadline->isLate($submission->submitted_at) => self::STATE_LATE,
            default => self::STATE_ON_TIME,
        };
    }

    public static function label(string $state): string
    {
        return match ($state) {
            self::STATE_NOT_SUBMITTED => 'Belum dikumpulkan',
            self::STATE_ON_TIME => 'Tepat waktu',
            self::STATE_LATE => 'Terlambat',
            self::STATE_FEEDBACK => 'Sudah ada feedback',
            default => $state,
        };
    }

    /**
     * Why the student may NOT change the submission (null = editable):
     * only before the effective deadline, while not reviewed and without
     * feedback. A late submission is therefore final once created.
     */
    public static function editBlocker(Submission $submission, AssignmentDeadline $deadline, bool $hasComments, ?CarbonInterface $now = null): ?string
    {
        return match (true) {
            $submission->status === SubmissionStatus::Reviewed => 'Pengumpulan sudah direview dan terkunci.',
            $hasComments => 'Pengumpulan sudah mendapat feedback dan terkunci.',
            $deadline->hasPassed($now) => 'Tenggat sudah lewat; pengumpulan tidak bisa diubah lagi.',
            default => null,
        };
    }

    /**
     * Why feedback may NOT be posted yet (null = allowed): only after the
     * effective deadline, and never on a late submission.
     */
    public static function commentBlocker(Submission $submission, AssignmentDeadline $deadline, ?CarbonInterface $now = null): ?string
    {
        return match (true) {
            $deadline->isLate($submission->submitted_at) => 'Terlambat - tidak mendapat feedback.',
            ! $deadline->hasPassed($now) => 'Feedback baru bisa diberikan setelah tenggat berakhir ('.$deadline->effective->translatedFormat('j F Y H:i').' WIB).',
            default => null,
        };
    }
}
