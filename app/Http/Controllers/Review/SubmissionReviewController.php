<?php

namespace App\Http\Controllers\Review;

use App\Actions\Assignments\BuildAssignmentReview;
use App\Actions\Assignments\ComputeAssignmentDeadline;
use App\Actions\Assignments\EditSubmissionComment;
use App\Actions\Assignments\PostSubmissionComment;
use App\Actions\Assignments\SubmissionRules;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmissionCommentRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\SubmissionCommentResource;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\SubmissionComment;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use App\Rules\AllowedImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reviewer screens: all submissions (staff/admin), one submission with its
 * feedback thread, and posting/editing/deleting comments.
 */
class SubmissionReviewController extends Controller
{
    /**
     * Every submission of every course (assignments.comment: staff, admin).
     */
    public function index(Request $request, ComputeAssignmentDeadline $deadlines): Response
    {
        abort_unless($request->user()?->can(Permission::AssignmentsComment->value), 403);

        $courseId = $request->integer('kursus') ?: null;
        $status = in_array($request->string('status')->toString(), ['submitted', 'reviewed'], true) ? $request->string('status')->toString() : null;
        $search = Str::of($request->string('q')->toString())->squish()->limit(100, '')->toString();
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $page = Submission::query()
            ->when($courseId, fn (Builder $query) => $query->whereHas('assignment', fn (Builder $assignment) => $assignment->where('course_id', $courseId)))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('enrollment.user', fn (Builder $user) => $user->where(fn (Builder $where) => $where
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]))))
            ->with(['assignment.lesson', 'assignment.course:id,title', 'enrollment.batch', 'enrollment.course', 'enrollment.user:id,name,email'])
            ->withCount('comments')
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();

        /** @var Collection<int, array{0: Assignment, 1: Enrollment}> $pairs */
        $pairs = collect($page->items())->map(fn (Submission $submission) => [$submission->assignment, $submission->enrollment])->values();
        $due = $deadlines->many($pairs);

        return Inertia::render('submissions/index', [
            'submissions' => $page->through(function (Submission $submission) use ($due): array {
                $deadline = $due[ComputeAssignmentDeadline::key($submission->assignment, $submission->enrollment)];
                $state = BuildAssignmentReview::reviewState($submission, $deadline);

                return [
                    'id' => $submission->id,
                    'student' => $submission->enrollment->user->name,
                    'email' => $submission->enrollment->user->email,
                    'assignment' => $submission->assignment->title,
                    'course' => $submission->assignment->course?->title,
                    'batch' => $submission->enrollment->batch?->name,
                    'submitted_at' => $submission->submitted_at->toIso8601String(),
                    'due_at' => $deadline->effective->toIso8601String(),
                    'state' => $state,
                    'state_label' => BuildAssignmentReview::reviewLabel($state),
                    'comments_count' => $submission->comments_count,
                ];
            }),
            'courses' => Course::query()->whereHas('assignments')->orderBy('title')->get(['id', 'title']),
            'filters' => ['kursus' => $courseId, 'status' => $status, 'q' => $search ?: null],
        ]);
    }

    public function show(Request $request, Submission $submission, ComputeAssignmentDeadline $deadlines, SubmissionPolicy $policy): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($policy->isReviewer($user, $submission), 404);

        $submission->load([
            'assignment.lesson', 'assignment.course', 'enrollment.batch', 'enrollment.course', 'enrollment.user:id,name,email',
            'attachments', 'comments.author:id,name', 'comments.attachments', 'reviewer:id,name',
        ]);
        $assignment = $submission->assignment;
        $deadline = $deadlines->for($assignment, $submission->enrollment);
        $commentBlocker = SubmissionRules::commentBlocker($submission, $deadline);
        $course = $assignment->course;

        return Inertia::render('submissions/show', [
            'submission' => [
                'id' => $submission->id,
                'body' => $submission->body,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'is_late' => $deadline->isLate($submission->submitted_at),
                'state' => BuildAssignmentReview::reviewState($submission, $deadline),
                'state_label' => BuildAssignmentReview::reviewLabel(BuildAssignmentReview::reviewState($submission, $deadline)),
                'reviewed_at' => $submission->reviewed_at?->toIso8601String(),
                'reviewer' => $submission->reviewer?->name,
                'images' => AttachmentResource::collection($submission->attachments)->resolve(),
                'comments' => SubmissionCommentResource::collection($submission->comments)->resolve(),
            ],
            'student' => [
                'name' => $submission->enrollment->user->name,
                'email' => $submission->enrollment->user->email,
                'batch' => $submission->enrollment->batch?->name,
            ],
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions_html' => $assignment->instructions_html,
                'is_final_project' => $assignment->is_final_project,
                'lesson' => $assignment->lesson?->title,
                'due_at' => $deadline->effective->toIso8601String(),
                'is_extended' => $deadline->source !== 'default',
            ],
            'course' => $course ? ['id' => $course->id, 'title' => $course->title] : null,
            // Every reviewer (instructor of the course, staff, admin) may open the class assignment pages.
            'backUrl' => $course
                ? route('classes.assignments.show', [$course, $assignment, 'batch' => $submission->enrollment->batch_id])
                : route('submissions.index'),
            'canComment' => $commentBlocker === null,
            'commentBlocker' => $commentBlocker,
            'imageLimits' => [
                'max_images' => (int) config('pixora.assignments.comment_max_images', 5),
                'max_image_kb' => (int) config('pixora.assignments.max_image_kb', 10240),
                'max_image_label' => AllowedImage::humanSize((int) config('pixora.assignments.max_image_kb', 10240)),
                'accept' => implode(',', AllowedImage::MIMES),
            ],
        ]);
    }

    public function storeComment(SubmissionCommentRequest $request, Submission $submission, PostSubmissionComment $post): RedirectResponse
    {
        Gate::authorize('comment', $submission);

        /** @var User $user */
        $user = $request->user();
        $post->handle($submission, $user, $request->validated('body'), $request->images());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Komentar terkirim.']);

        return back();
    }

    public function updateComment(Request $request, SubmissionComment $comment, EditSubmissionComment $edit): RedirectResponse
    {
        Gate::authorize('update', $comment);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000']], [], ['body' => 'komentar']);

        $edit->handle($comment, $data['body'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Komentar diperbarui.']);

        return back();
    }

    public function destroyComment(SubmissionComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Komentar dihapus.']);

        return back();
    }
}
