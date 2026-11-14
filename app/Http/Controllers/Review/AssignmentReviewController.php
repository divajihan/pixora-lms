<?php

namespace App\Http\Controllers\Review;

use App\Actions\Assignments\BuildAssignmentReview;
use App\Actions\Assignments\SetDeadlineOverride;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\AssignmentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /kelas/{course}/tugas: assignments of a course per batch with counts, the
 * students of one assignment, and deadline extensions (per batch / student).
 * Viewing: instructors of the course, staff, admins. Extensions: instructors
 * of the course and admins (AssignmentPolicy::manageDeadlines).
 */
class AssignmentReviewController extends Controller
{
    public function index(Request $request, Course $course, BuildAssignmentReview $review): Response
    {
        $user = $this->reviewer($request, $course);
        $batch = $this->batch($request, $course);

        return Inertia::render('classes/assignments', [
            'course' => $this->courseProps($course),
            'batches' => $this->batchOptions($course),
            'selectedBatchId' => $batch?->id,
            'assignments' => $review->overview($course, $batch),
            'canManageDeadlines' => $user->can('manageDeadlines', new Assignment(['course_id' => $course->id])),
        ]);
    }

    public function show(Request $request, Course $course, Assignment $assignment, BuildAssignmentReview $review): Response
    {
        $user = $this->reviewer($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);
        $batch = $this->batch($request, $course);

        $filter = $request->string('status')->toString() ?: null;
        $search = $request->string('q')->squish()->toString() ?: null;
        $overview = collect($review->overview($course, $batch))->firstWhere('id', $assignment->id);

        return Inertia::render('classes/assignment-students', [
            'course' => $this->courseProps($course),
            'batches' => $this->batchOptions($course),
            'selectedBatchId' => $batch?->id,
            'assignment' => $overview,
            'students' => $review->students($assignment, $batch, $filter, $search, max(1, $request->integer('page', 1))),
            'filters' => ['status' => $filter, 'q' => $search],
            'statusOptions' => collect(BuildAssignmentReview::FILTERS)->map(fn (string $state, string $key) => ['value' => $key, 'label' => BuildAssignmentReview::reviewLabel($state)])->values(),
            'canManageDeadlines' => $user->can('manageDeadlines', $assignment),
        ]);
    }

    public function setDeadline(Request $request, Course $course, Assignment $assignment, SetDeadlineOverride $set): RedirectResponse
    {
        [$batch, $enrollment] = $this->deadlineScope($request, $course, $assignment);
        $data = $request->validate(['due_at' => ['required', 'date']], [], ['due_at' => 'tenggat baru']);

        /** @var User $user */
        $user = $request->user();
        $set->set($assignment, $batch, $enrollment, CarbonImmutable::parse((string) $data['due_at'], config('app.timezone')), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tenggat diperpanjang.']);

        return back();
    }

    public function resetDeadline(Request $request, Course $course, Assignment $assignment, SetDeadlineOverride $set): RedirectResponse
    {
        [$batch, $enrollment] = $this->deadlineScope($request, $course, $assignment);
        $set->reset($assignment, $batch, $enrollment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tenggat dikembalikan ke jadwal semula.']);

        return back();
    }

    /**
     * @return array{0: Batch|null, 1: Enrollment|null}
     */
    private function deadlineScope(Request $request, Course $course, Assignment $assignment): array
    {
        abort_unless($assignment->course_id === $course->id, 404);
        Gate::authorize('manageDeadlines', $assignment);

        $data = $request->validate([
            'batch_id' => ['nullable', 'integer', 'required_without:enrollment_id'],
            'enrollment_id' => ['nullable', 'integer'],
        ], ['batch_id.required_without' => 'Pilih batch atau siswa.']);

        $enrollment = isset($data['enrollment_id'])
            ? Enrollment::where('course_id', $course->id)->whereKey((int) $data['enrollment_id'])->firstOrFail()
            : null;
        $batch = $enrollment === null && isset($data['batch_id'])
            ? $course->batches()->whereKey((int) $data['batch_id'])->firstOrFail()
            : null;

        return [$batch, $enrollment];
    }

    private function reviewer(Request $request, Course $course): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(AssignmentPolicy::canReviewCourse($user, $course), 404);

        return $user;
    }

    /**
     * Drip courses are reviewed per batch: ?batch=ID, else the running batch,
     * else the most recently started one, else the first. Non-drip: null.
     */
    private function batch(Request $request, Course $course): ?Batch
    {
        if (! $course->drip_enabled) {
            return null;
        }

        $batches = $course->batches()->orderBy('starts_on')->get();

        if ($request->filled('batch')) {
            return $batches->firstWhere('id', $request->integer('batch')) ?? abort(404);
        }

        $today = now()->toDateString();

        return $batches->first(fn (Batch $batch) => $batch->starts_on->toDateString() <= $today && $batch->ends_on->toDateString() >= $today)
            ?? $batches->filter(fn (Batch $batch) => $batch->starts_on->toDateString() <= $today)->last()
            ?? $batches->first();
    }

    /**
     * @return array<int, array{id: int, name: string, status_label: string}>
     */
    private function batchOptions(Course $course): array
    {
        if (! $course->drip_enabled) {
            return [];
        }

        return $course->batches()->orderBy('starts_on')->get()
            ->map(fn (Batch $batch) => ['id' => $batch->id, 'name' => $batch->name, 'status_label' => $batch->status()->label()])
            ->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function courseProps(Course $course): array
    {
        return ['id' => $course->id, 'title' => $course->title, 'slug' => $course->slug, 'drip_enabled' => $course->drip_enabled];
    }
}
