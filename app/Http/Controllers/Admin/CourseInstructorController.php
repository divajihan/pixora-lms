<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Courses\AssignInstructor;
use App\Enums\CourseInstructorRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignInstructorRequest;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CourseInstructorController extends Controller
{
    public function store(AssignInstructorRequest $request, Course $course, AssignInstructor $assign): RedirectResponse
    {
        $user = User::findOrFail($request->integer('user_id'));

        $assign->handle($course, $user, CourseInstructorRole::from((string) $request->validated('role')));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} ditugaskan ke kursus ini."]);

        return back();
    }

    public function destroy(Course $course, User $user, AssignInstructor $assign): RedirectResponse
    {
        Gate::authorize('update', $course);

        $assign->remove($course, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} dilepas dari kursus ini."]);

        return back();
    }
}
