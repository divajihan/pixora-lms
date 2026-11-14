<?php

namespace App\Actions\Assignments;

use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reviewer views of a course (instructor of the course, staff, admin), scoped
 * to one batch (drip courses) or all students (non-drip):
 *   - overview(): per assignment, counts of not submitted / on time awaiting
 *     review / reviewed / late, plus the batch-level deadline;
 *   - students(): one assignment's students with state, filters, search and
 *     pagination.
 * Late is dynamic, so states are computed in PHP from one set of queries
 * (enrollments + submissions + bulk deadlines) — constant query count.
 */
class BuildAssignmentReview
{
    public const REVIEW_NOT_SUBMITTED = 'not_submitted';

    public const REVIEW_ON_TIME = 'on_time';

    public const REVIEW_NEEDS_REVIEW = 'needs_review';

    public const REVIEW_REVIEWED = 'reviewed';

    public const REVIEW_LATE = 'late';

    public const FILTERS = [
        'belum' => self::REVIEW_NOT_SUBMITTED,
        'tepat-waktu' => self::REVIEW_ON_TIME,
        'perlu-review' => self::REVIEW_NEEDS_REVIEW,
        'direview' => self::REVIEW_REVIEWED,
        'terlambat' => self::REVIEW_LATE,
    ];

    public function __construct(private readonly ComputeAssignmentDeadline $deadlines) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function overview(Course $course, ?Batch $batch): array
    {
        $assignments = $course->assignments()->with('lesson')->orderByRaw('lesson_id is null')->orderBy('id')->get();
        $enrollments = $this->enrollments($course, $batch)->get();
        $rows = $this->rows($assignments, $enrollments);

        return $assignments->map(function (Assignment $assignment) use ($rows, $batch): array {
            $states = $rows->where('assignment_id', $assignment->id)->countBy('review_state');
            $batchDeadline = $this->deadlines->forBatch($assignment, $batch);

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'status' => $assignment->status->value,
                'status_label' => $assignment->status->label(),
                'is_final_project' => $assignment->is_final_project,
                'lesson' => $assignment->lesson ? ['title' => $assignment->lesson->title, 'day_number' => $assignment->lesson->dayNumber()] : null,
                'due_offset_days' => $assignment->due_offset_days,
                'deadline' => $batchDeadline ? [
                    'default' => $batchDeadline['default']->toIso8601String(),
                    'override' => $batchDeadline['override']?->toIso8601String(),
                    'effective' => $batchDeadline['effective']->toIso8601String(),
                    'has_passed' => $batchDeadline['effective']->isPast(),
                ] : null,
                'counts' => [
                    'not_submitted' => $states->get(self::REVIEW_NOT_SUBMITTED, 0),
                    'awaiting_review' => $states->get(self::REVIEW_ON_TIME, 0) + $states->get(self::REVIEW_NEEDS_REVIEW, 0),
                    'needs_review' => $states->get(self::REVIEW_NEEDS_REVIEW, 0),
                    'reviewed' => $states->get(self::REVIEW_REVIEWED, 0),
                    'late' => $states->get(self::REVIEW_LATE, 0),
                ],
            ];
        })->values()->all();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function students(Assignment $assignment, ?Batch $batch, ?string $filter, ?string $search, int $page, int $perPage = 20): LengthAwarePaginator
    {
        $assignment->loadMissing('lesson');
        $query = $this->enrollments($assignment->course, $batch);

        if ($search !== null && $search !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::limit($search, 100, '')).'%';
            $query->whereHas('user', fn (Builder $user) => $user->where(fn (Builder $where) => $where
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like])));
        }

        $rows = $this->rows(new EloquentCollection([$assignment]), $query->get());
        $state = self::FILTERS[$filter ?? ''] ?? null;

        if ($state !== null) {
            $rows = $rows->filter(fn (array $row) => $state === self::REVIEW_ON_TIME
                ? in_array($row['review_state'], [self::REVIEW_ON_TIME, self::REVIEW_NEEDS_REVIEW], true)
                : $row['review_state'] === $state);
        }

        $rows = $rows->sortBy([['sort', 'asc'], ['name', 'asc']])->values();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values()->all(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * Students in scope: active/completed enrollments of the course, in the batch (drip).
     *
     * @return Builder<Enrollment>
     */
    private function enrollments(Course $course, ?Batch $batch): Builder
    {
        return Enrollment::query()
            ->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->when($batch !== null, fn (Builder $query) => $query->where('batch_id', $batch?->id))
            ->with(['user:id,name,email', 'batch', 'course']);
    }

    /**
     * One row per (assignment, enrollment) with the reviewer state.
     *
     * @param  EloquentCollection<int, Assignment>  $assignments
     * @param  EloquentCollection<int, Enrollment>  $enrollments
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(EloquentCollection $assignments, EloquentCollection $enrollments): Collection
    {
        if ($assignments->isEmpty() || $enrollments->isEmpty()) {
            return collect();
        }

        $submissions = Submission::query()
            ->whereIn('assignment_id', $assignments->modelKeys())
            ->whereIn('enrollment_id', $enrollments->modelKeys())
            ->withCount('comments')
            ->get()
            ->keyBy(fn (Submission $submission) => $submission->assignment_id.':'.$submission->enrollment_id);

        /** @var Collection<int, array{0: Assignment, 1: Enrollment}> $pairs */
        $pairs = $assignments->crossJoin($enrollments)->values();
        $deadlines = $this->deadlines->many($pairs);

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $pairs->map(function (array $pair) use ($submissions, $deadlines): array {
            [$assignment, $enrollment] = $pair;
            $deadline = $deadlines[ComputeAssignmentDeadline::key($assignment, $enrollment)];
            $submission = $submissions->get($assignment->id.':'.$enrollment->id);
            $state = self::reviewState($submission, $deadline);

            return [
                'assignment_id' => $assignment->id,
                'enrollment_id' => $enrollment->id,
                'name' => $enrollment->user->name,
                'email' => $enrollment->user->email,
                'batch' => $enrollment->batch?->name,
                'review_state' => $state,
                'review_label' => self::reviewLabel($state),
                'sort' => array_search($state, [self::REVIEW_NEEDS_REVIEW, self::REVIEW_ON_TIME, self::REVIEW_LATE, self::REVIEW_NOT_SUBMITTED, self::REVIEW_REVIEWED], true),
                'submission_id' => $submission?->id,
                'submitted_at' => $submission?->submitted_at->toIso8601String(),
                'comments_count' => $submission->comments_count ?? 0,
                'due_at' => $deadline->effective->toIso8601String(),
                'deadline_source' => $deadline->source,
                'student_due_override' => $deadline->enrollmentOverride?->toIso8601String(),
            ];
        });

        return $rows;
    }

    public static function reviewState(?Submission $submission, AssignmentDeadline $deadline): string
    {
        return match (true) {
            $submission === null => self::REVIEW_NOT_SUBMITTED,
            $submission->status === SubmissionStatus::Reviewed => self::REVIEW_REVIEWED,
            $deadline->isLate($submission->submitted_at) => self::REVIEW_LATE,
            $deadline->hasPassed() => self::REVIEW_NEEDS_REVIEW,
            default => self::REVIEW_ON_TIME,
        };
    }

    public static function reviewLabel(string $state): string
    {
        return match ($state) {
            self::REVIEW_NOT_SUBMITTED => 'Belum mengumpulkan',
            self::REVIEW_ON_TIME => 'Tepat waktu',
            self::REVIEW_NEEDS_REVIEW => 'Perlu direview',
            self::REVIEW_REVIEWED => 'Sudah direview',
            self::REVIEW_LATE => 'Terlambat',
            default => $state,
        };
    }
}
