<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Courses\ChangeCourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeCourseStatusRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CourseStatusController extends Controller
{
    public function __invoke(ChangeCourseStatusRequest $request, Course $course, ChangeCourseStatus $changeStatus): RedirectResponse
    {
        $changeStatus->handle($course, $request->target());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Status kursus menjadi \"{$course->status->label()}\"."]);

        return back();
    }
}
