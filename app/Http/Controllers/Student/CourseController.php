<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Kursus saya" and the learning area placeholder (player arrives in Phase 4).
 */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $enrollments = $user->enrollments()
            ->with('course:id,title,slug,thumbnail_path,category_id', 'course.category:id,name')
            ->latest('enrolled_at')
            ->get()
            ->map(fn (Enrollment $enrollment) => [
                'id' => $enrollment->id,
                'status' => $enrollment->status->value,
                'status_label' => $enrollment->status->label(),
                'is_accessible' => $enrollment->grantsAccess(),
                'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
                'expires_at' => $enrollment->expires_at?->toIso8601String(),
                'progress_percent' => $enrollment->progress_percent,
                'course' => [
                    'title' => $enrollment->course->title,
                    'slug' => $enrollment->course->slug,
                    'thumbnail_url' => $enrollment->course->thumbnail_url,
                    'category' => $enrollment->course->category?->name,
                ],
            ]);

        return Inertia::render('student/courses/index', [
            'enrollments' => $enrollments,
        ]);
    }

    public function learn(Request $request, Course $course): Response
    {
        /** @var User $user */
        $user = $request->user();

        $enrollment = $user->enrollments()->where('course_id', $course->id)->first();

        abort_if($enrollment === null, 404);

        Gate::authorize('learn', $enrollment);

        return Inertia::render('student/courses/learn', [
            'course' => ['title' => $course->title, 'slug' => $course->slug],
            'enrollment' => [
                'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
                'expires_at' => $enrollment->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
