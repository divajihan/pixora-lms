<?php

namespace App\Enums;

/**
 * Stored submission state. "Late" is NOT stored: it is computed from
 * submitted_at and the current effective deadline (ComputeAssignmentDeadline).
 */
enum SubmissionStatus: string
{
    case Submitted = 'submitted';
    // An instructor of the course commented: the submission is locked.
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Dikumpulkan',
            self::Reviewed => 'Sudah direview',
        };
    }
}
