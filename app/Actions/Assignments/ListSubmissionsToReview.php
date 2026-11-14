<?php

namespace App\Actions\Assignments;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Perlu direview" for an instructor: on-time submissions in their courses
 * whose deadline has passed and that no instructor has commented on yet
 * (status still submitted). Oldest deadline first.
 */
class ListSubmissionsToReview
{
    public function __construct(private readonly ComputeAssignmentDeadline $deadlines) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(User $instructor): Collection
    {
        $courseIds = $instructor->taughtCourses()->pluck('courses.id');

        $submissions = Submission::query()
            ->where('status', SubmissionStatus::Submitted)
            ->whereHas('assignment', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->with(['assignment.lesson', 'assignment.course:id,title', 'enrollment.batch', 'enrollment.course', 'enrollment.user:id,name'])
            ->get();

        /** @var Collection<int, array{0: Assignment, 1: Enrollment}> $pairs */
        $pairs = $submissions->map(fn (Submission $submission) => [$submission->assignment, $submission->enrollment])->values();
        $deadlines = $this->deadlines->many($pairs);

        /** @var Collection<int, array<string, mixed>> $queue */
        $queue = $submissions
            ->map(function (Submission $submission) use ($deadlines): ?array {
                $deadline = $deadlines[ComputeAssignmentDeadline::key($submission->assignment, $submission->enrollment)];

                if (BuildAssignmentReview::reviewState($submission, $deadline) !== BuildAssignmentReview::REVIEW_NEEDS_REVIEW) {
                    return null;
                }

                return [
                    'id' => $submission->id,
                    'student' => $submission->enrollment->user->name,
                    'assignment' => $submission->assignment->title,
                    'course' => $submission->assignment->course?->title,
                    'batch' => $submission->enrollment->batch?->name,
                    'due_at' => $deadline->effective->toIso8601String(),
                    'submitted_at' => $submission->submitted_at->toIso8601String(),
                ];
            })
            ->filter()
            ->sortBy('due_at')
            ->values();

        return $queue;
    }
}
