<?php

namespace App\Actions\Assignments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Result of ComputeAssignmentDeadline for one student (or one batch).
 */
final readonly class AssignmentDeadline
{
    public const SOURCE_DEFAULT = 'default';

    public const SOURCE_BATCH = 'batch';

    public const SOURCE_ENROLLMENT = 'enrollment';

    public function __construct(
        // Date the countdown starts from (lesson opening / batch end / enrollment).
        public CarbonImmutable $reference,
        public CarbonImmutable $default,
        public ?CarbonImmutable $batchOverride,
        public ?CarbonImmutable $enrollmentOverride,
        public CarbonImmutable $effective,
        public string $source,
    ) {}

    public function hasPassed(?CarbonInterface $now = null): bool
    {
        return $this->effective->lt($now ?? now());
    }

    /**
     * Late is computed, never stored: an extension fixes it immediately.
     */
    public function isLate(CarbonInterface $submittedAt): bool
    {
        return $submittedAt->gt($this->effective);
    }
}
