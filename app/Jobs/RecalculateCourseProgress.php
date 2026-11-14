<?php

namespace App\Jobs;

use App\Actions\Learning\RecalculateEnrollmentProgress;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recomputes progress for all active/completed enrollments of a course after
 * lessons are published, unpublished or deleted. Unique per course so a burst
 * of changes (e.g. deleting a module) runs once.
 */
class RecalculateCourseProgress implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public function __construct(public readonly int $courseId) {}

    public function uniqueId(): string
    {
        return (string) $this->courseId;
    }

    public function handle(RecalculateEnrollmentProgress $recalculate): void
    {
        Enrollment::query()
            ->where('course_id', $this->courseId)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->chunkById(200, function ($enrollments) use ($recalculate): void {
                foreach ($enrollments as $enrollment) {
                    $recalculate->handle($enrollment);
                }
            });
    }
}
