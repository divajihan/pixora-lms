<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Curriculum\SaveLesson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LessonController extends Controller
{
    public function store(LessonRequest $request, Module $module, SaveLesson $saveLesson): RedirectResponse
    {
        $saveLesson->handle($module, null, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lesson ditambahkan.']);

        return back();
    }

    public function update(LessonRequest $request, Lesson $lesson, SaveLesson $saveLesson): RedirectResponse
    {
        $saveLesson->handle($lesson->module, $lesson, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lesson diperbarui.']);

        return back();
    }

    /**
     * Soft delete: the lesson is archived, not removed permanently.
     */
    public function destroy(Lesson $lesson): RedirectResponse
    {
        Gate::authorize('delete', $lesson);

        $lesson->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lesson \"{$lesson->title}\" dihapus."]);

        return back();
    }
}
