<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Curriculum\LoadCurriculum;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Kursus saya" for instructors: read-only in Phase 2.
 * Authorization: route `role:instructor` + CoursePolicy (assigned courses only).
 */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAssigned', Course::class);

        /** @var User $user */
        $user = $request->user();

        $courses = $user->taughtCourses()
            ->with('category:id,name')
            ->withCount(['modules', 'lessons'])
            ->orderBy('title')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'status' => $course->status->value,
                'status_label' => $course->status->label(),
                'category' => $course->category?->name,
                'role' => $course->getRelationValue('assignment')?->role?->label(),
                'modules_count' => $course->modules_count,
                'lessons_count' => $course->lessons_count,
                'is_public' => $course->isPublished(),
            ]);

        return Inertia::render('instructor/courses/index', [
            'courses' => $courses,
        ]);
    }

    public function show(Course $course, LoadCurriculum $loadCurriculum): Response
    {
        Gate::authorize('viewCurriculum', $course);

        return Inertia::render('instructor/courses/show', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'status' => $course->status->value,
                'status_label' => $course->status->label(),
                'is_public' => $course->isPublished(),
                'drip_enabled' => (bool) $course->drip_enabled,
            ],
            ...$loadCurriculum->handle($course),
        ]);
    }
}
