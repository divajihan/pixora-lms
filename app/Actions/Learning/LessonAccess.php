<?php

namespace App\Actions\Learning;

use Carbon\CarbonImmutable;

/**
 * Result of ComputeLessonAccess for one lesson and one enrollment.
 */
final readonly class LessonAccess
{
    public const SOURCE_IMMEDIATE = 'immediate';     // non-drip course

    public const SOURCE_PREVIEW = 'preview';         // free preview lesson

    public const SOURCE_DEFAULT = 'default';         // batch start + offset

    public const SOURCE_BATCH = 'batch_override';

    public const SOURCE_ENROLLMENT = 'enrollment_override';

    public function __construct(
        public bool $unlocked,
        /** Null when open without a schedule (non-drip course or preview). */
        public ?CarbonImmutable $unlockAt,
        public string $source,
    ) {}
}
