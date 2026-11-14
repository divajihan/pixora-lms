<?php

namespace App\Actions\Assignments;

use App\Actions\Learning\ComputeLessonAccess;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Tugas saya": published assignments of the student's accessible courses
 * with effective deadline, state and a "new feedback" flag. Assignments that
 * are not open yet are listed with their opening date (no instructions).
 *
 * Query count does not grow with the number of assignments or submissions
 * (one unlock-override query per drip course with a batch).
 */
class ListStudentAssignments
{
    public function __construct(
        private readonly ComputeLessonAccess $lessonAccess,
        private readonly AssignmentAvailability $availability,
        private readonly ComputeAssignmentDeadline $deadlines,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(User $user): array
    {
        $enrollments = $user->enrollments()->with(['course', 'batch'])->get()
            ->filter(fn (Enrollment $enrollment) => $this->lessonAccess->canAccessCourse($enrollment))
            ->keyBy('course_id');

        if ($enrollments->isEmpty()) {
            return [];
        }

        $assignments = Assignment::query()
            ->published()
            ->whereIn('course_id', $enrollments->keys())
            ->with(['lesson.module'])
            ->get();

        $submissions = Submission::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->withCount('comments')
            ->withMax('comments', 'updated_at')
            ->get()
            ->keyBy(fn (Submission $submission) => $submission->assignment_id.':'.$submission->enrollment_id);

        /** @var array<int, bool|CarbonImmutable> $opens */
        $opens = [];
        foreach ($assignments->groupBy('course_id') as $courseId => $group) {
            $opens += $this->availability->forEnrollment($enrollments[$courseId], $group);
        }

        $visible = $assignments->filter(fn (Assignment $assignment) => $opens[$assignment->id] !== false)->values();
        /** @var Collection<int, array{0: Assignment, 1: Enrollment}> $pairs */
        $pairs = $visible->map(fn (Assignment $assignment) => [$assignment, $enrollments[$assignment->course_id]]);
        $deadlines = $this->deadlines->many($pairs);

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $visible->map(function (Assignment $assignment) use ($enrollments, $submissions, $opens, $deadlines): array {
            $enrollment = $enrollments[$assignment->course_id];
            $deadline = $deadlines[ComputeAssignmentDeadline::key($assignment, $enrollment)];
            $submission = $submissions->get($assignment->id.':'.$enrollment->id);
            $opensAt = $opens[$assignment->id];
            $state = SubmissionRules::state($submission, $deadline, ($submission->comments_count ?? 0) > 0);
            $course = $enrollment->course;

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'is_final_project' => $assignment->is_final_project,
                'course' => ['title' => $course?->title, 'slug' => $course?->slug],
                'lesson' => $assignment->lesson ? ['title' => $assignment->lesson->title, 'slug' => $assignment->lesson->slug] : null,
                'opens_at' => $opensAt instanceof CarbonImmutable ? $opensAt->toIso8601String() : null,
                'due_at' => $deadline->effective->toIso8601String(),
                'is_extended' => $deadline->source !== AssignmentDeadline::SOURCE_DEFAULT,
                'has_passed' => $deadline->hasPassed(),
                'state' => $state,
                'state_label' => SubmissionRules::label($state),
                'submitted_at' => $submission?->submitted_at->toIso8601String(),
                'has_new_feedback' => $submission !== null && ($submission->comments_count ?? 0) > 0 && $submission->hasUnseenFeedback(),
            ];
        });

        return $this->sort($rows);
    }

    /**
     * To do first (open, not submitted, nearest deadline), then upcoming, then done.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sort(Collection $rows): array
    {
        return array_values($rows->sortBy(fn (array $row) => [
            match (true) {
                $row['has_new_feedback'] => 0,
                $row['opens_at'] === null && $row['state'] === SubmissionRules::STATE_NOT_SUBMITTED => 1,
                $row['opens_at'] !== null => 2,
                default => 3,
            },
            $row['due_at'],
        ])->all());
    }
}
