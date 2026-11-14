<?php

namespace App\Http\Controllers\Student;

use App\Actions\Assignments\AssignmentAvailability;
use App\Actions\Assignments\BuildAssignmentPanel;
use App\Actions\Assignments\ListStudentAssignments;
use App\Actions\Assignments\SubmitAssignment;
use App\Actions\Assignments\UpdateSubmission;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmissionRequest;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student side of assignments: "Tugas saya", the final project page and
 * submitting/updating images. Lesson assignments are shown on the lesson
 * page (LearnController). Only for the owner of an accessible enrollment.
 */
class AssignmentController extends Controller
{
    public function index(Request $request, ListStudentAssignments $list): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('student/assignments/index', [
            'assignments' => $list->handle($user),
        ]);
    }

    public function finalProject(Request $request, Course $course, AssignmentAvailability $availability, BuildAssignmentPanel $panel): Response
    {
        $enrollment = $this->enrollment($request, $course);

        $project = $course->assignments()->whereNull('lesson_id')->where('status', AssignmentStatus::Published)->first();
        abort_if($project === null, 404);

        $opensAt = $availability->opensAt($enrollment, $project);
        abort_if($opensAt === false, 404);

        return Inertia::render('learn/final-project', [
            'course' => ['title' => $course->title, 'slug' => $course->slug],
            'opensAt' => $opensAt === true ? null : $opensAt->toIso8601String(),
            'panel' => $opensAt === true ? $panel->handle($enrollment, $project) : null,
            'title' => $project->title,
        ]);
    }

    public function store(SubmissionRequest $request, Course $course, Assignment $assignment, SubmitAssignment $submit): RedirectResponse
    {
        $enrollment = $this->enrollment($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);

        /** @var User $user */
        $user = $request->user();
        $submit->handle($enrollment, $assignment, $request->images(), $request->validated('body'), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas berhasil dikumpulkan.']);

        return back();
    }

    public function update(SubmissionRequest $request, Course $course, Assignment $assignment, UpdateSubmission $update): RedirectResponse
    {
        $enrollment = $this->enrollment($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);

        $submission = Submission::where('assignment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->firstOrFail();
        Gate::authorize('update', $submission);

        /** @var User $user */
        $user = $request->user();
        /** @var list<int> $remove */
        $remove = array_map('intval', (array) $request->validated('remove', []));
        $update->handle($submission, $request->images(), $remove, $request->validated('body'), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumpulan diperbarui.']);

        return back();
    }

    /**
     * Same rule as the player: 404 without an enrollment, 403 when access ended.
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
}
