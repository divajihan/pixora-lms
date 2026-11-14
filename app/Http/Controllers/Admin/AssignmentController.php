<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\SaveAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Assignments in the curriculum builder (courses.manage): one per lesson and
 * one final project per course.
 */
class AssignmentController extends Controller
{
    public function store(AssignmentRequest $request, Course $course, SaveAssignment $save): RedirectResponse
    {
        /** @var array{lesson_id?: int|string|null, title: string, instructions_html?: string|null, due_offset_days: int|string, max_images: int|string, is_required: bool, status: string} $data */
        $data = $request->validated();
        $assignment = $save->handle($course, null, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => $assignment->is_final_project ? 'Proyek akhir disimpan.' : 'Tugas disimpan.']);

        return back();
    }

    public function update(AssignmentRequest $request, Assignment $assignment, SaveAssignment $save): RedirectResponse
    {
        /** @var array{title: string, instructions_html?: string|null, due_offset_days: int|string, max_images: int|string, is_required: bool, status: string} $data */
        $data = $request->safe()->except('lesson_id');
        $save->handle($assignment->course, $assignment, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas diperbarui.']);

        return back();
    }

    public function destroy(Assignment $assignment, SaveAssignment $save): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $save->delete($assignment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas dihapus.']);

        return back();
    }
}
