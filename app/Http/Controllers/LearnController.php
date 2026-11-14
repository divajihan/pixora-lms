<?php

namespace App\Http\Controllers;

use App\Actions\Learning\BuildCourseOutline;
use App\Actions\Learning\MarkLessonCompleted;
use App\Actions\Learning\UnmarkLessonCompleted;
use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Video\VideoProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The learning player (/belajar/...). Access: the owner of an accessible
 * enrollment (EnrollmentPolicy::learn); lesson content only when
 * LessonPolicy::viewContent allows it (ComputeLessonAccess).
 *
 * SECURITY: video, summary_html and meeting_url are only put in the props when
 * the lesson is open for this student.
 */
class LearnController extends Controller
{
    public function course(Request $request, Course $course, BuildCourseOutline $outline): Response
    {
        $enrollment = $this->enrollment($request, $course);
        $data = $outline->handle($enrollment);

        return Inertia::render('learn/course', [
            'course' => $this->courseProps($course),
            'enrollment' => $this->enrollmentProps($enrollment),
            'modules' => $data['modules'],
            'counts' => $data['counts'],
            'continueSlug' => $data['continue_slug'],
        ]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson, BuildCourseOutline $outline, VideoProvider $video): Response
    {
        $enrollment = $this->enrollment($request, $course);

        abort_unless($lesson->status === LessonStatus::Published && $lesson->module()->exists(), 404);

        $data = $outline->handle($enrollment);
        $status = $data['statuses'][$lesson->id] ?? null;
        abort_if($status === null, 404);

        $canView = Gate::allows('viewContent', $lesson);

        $ordered = $data['lessons']->values();
        $index = $ordered->search(fn (Lesson $item) => $item->id === $lesson->id);
        $neighbour = fn (?Lesson $item) => $item ? [
            'slug' => $item->slug,
            'title' => $item->title,
            'is_unlocked' => $data['statuses'][$item->id]['status'] !== 'locked',
        ] : null;

        return Inertia::render('learn/lesson', [
            'course' => $this->courseProps($course),
            'enrollment' => $this->enrollmentProps($enrollment),
            'modules' => $data['modules'],
            'lesson' => $status,
            'content' => $canView ? $this->content($lesson, $video) : null,
            'previous' => $neighbour($index > 0 ? $ordered[$index - 1] : null),
            'next' => $neighbour($ordered[$index + 1] ?? null),
        ]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson, MarkLessonCompleted $mark): RedirectResponse
    {
        $enrollment = $this->enrollment($request, $course);
        Gate::authorize('complete', $lesson);

        $mark->handle($enrollment, $lesson);

        return back();
    }

    public function uncomplete(Request $request, Course $course, Lesson $lesson, UnmarkLessonCompleted $unmark): RedirectResponse
    {
        $enrollment = $this->enrollment($request, $course);
        Gate::authorize('complete', $lesson);

        $unmark->handle($enrollment, $lesson);

        return back();
    }

    /**
     * The viewer's enrollment for this course: 404 when none (not discoverable),
     * 403 when expired/revoked/course is a draft.
     */
    private function enrollment(Request $request, Course $course): Enrollment
    {
        /** @var User $user */
        $user = $request->user();

        $enrollment = $user->enrollments()->where('course_id', $course->id)->with(['course', 'batch'])->first();
        abort_if($enrollment === null, 404);

        Gate::authorize('learn', $enrollment);

        return $enrollment;
    }

    /**
     * Protected lesson content — only for open lessons.
     *
     * @return array<string, mixed>
     */
    private function content(Lesson $lesson, VideoProvider $video): array
    {
        $lesson->loadMissing('liveSession');
        $live = $lesson->type === LessonType::Live ? $lesson->liveSession : null;

        return [
            'embed_url' => $lesson->type === LessonType::Video && $lesson->video_ref ? $video->embedUrl($lesson->video_ref) : null,
            // Sanitised on save (HtmlSanitizer).
            'summary_html' => $lesson->summary_html,
            'live' => $live ? [
                'starts_at' => $live->starts_at->toIso8601String(),
                'ends_at' => $live->ends_at?->toIso8601String(),
                'platform' => $live->platform,
                'meeting_url' => $live->meeting_url,
                'notes' => $live->notes,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseProps(Course $course): array
    {
        return ['title' => $course->title, 'slug' => $course->slug, 'drip_enabled' => $course->drip_enabled];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentProps(Enrollment $enrollment): array
    {
        return [
            'progress_percent' => $enrollment->progress_percent,
            'expires_at' => $enrollment->expires_at?->toIso8601String(),
            'batch' => $enrollment->batch ? [
                'name' => $enrollment->batch->name,
                'starts_on' => $enrollment->batch->starts_on->toDateString(),
                'ends_on' => $enrollment->batch->ends_on->toDateString(),
                'status_label' => $enrollment->batch->status()->label(),
            ] : null,
        ];
    }
}
