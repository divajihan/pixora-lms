<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Curriculum\LoadCurriculum;
use App\Actions\Curriculum\ReorderCurriculum;
use App\Enums\AssignmentStatus;
use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderCurriculumRequest;
use App\Http\Resources\Admin\AssignmentFormResource;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumController extends Controller
{
    public function show(Course $course, LoadCurriculum $loadCurriculum): Response
    {
        Gate::authorize('update', $course);

        return Inertia::render('admin/courses/curriculum', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'status' => $course->status->value,
                'status_label' => $course->status->label(),
                'drip_enabled' => $course->drip_enabled,
            ],
            ...$loadCurriculum->handle($course),
            'lessonTypes' => array_map(fn (LessonType $type) => ['value' => $type->value, 'label' => $type->label()], LessonType::cases()),
            'lessonStatuses' => array_map(fn (LessonStatus $status) => ['value' => $status->value, 'label' => $status->label()], LessonStatus::cases()),
            'finalProject' => ($project = $course->assignments()->whereNull('lesson_id')->withCount('submissions')->first())
                ? AssignmentFormResource::make($project)->resolve()
                : null,
            'assignmentStatuses' => array_map(fn (AssignmentStatus $status) => ['value' => $status->value, 'label' => $status->label()], AssignmentStatus::cases()),
            'assignmentMaxImages' => (int) config('pixora.assignments.max_images', 5),
        ]);
    }

    public function reorder(ReorderCurriculumRequest $request, Course $course, ReorderCurriculum $reorder): RedirectResponse
    {
        /** @var list<array{id: int, lessons: list<int>}> $modules */
        $modules = $request->validated('modules');

        $reorder->handle($course, $modules);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Urutan kurikulum disimpan.']);

        return back();
    }
}
