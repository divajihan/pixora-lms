<?php

namespace App\Http\Controllers\Public;

use App\Actions\Catalog\CatalogFilters;
use App\Actions\Catalog\PublicCurriculum;
use App\Actions\Catalog\SearchCourses;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseCardResource;
use App\Http\Resources\CourseDetailResource;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public catalog. Authorization: CoursePolicy (route middleware `can:view,course`).
 */
class CourseController extends Controller
{
    public function index(Request $request, SearchCourses $searchCourses): Response
    {
        $filters = CatalogFilters::fromRequest($request);

        $courses = $searchCourses->handle($filters)
            ->through(fn (Course $course) => CourseCardResource::make($course)->resolve());

        return Inertia::render('public/courses/index', [
            'meta' => [
                'title' => 'Katalog Kursus',
                'description' => 'Jelajahi semua kursus online di '.config('app.name').'. Cari berdasarkan judul, kategori, atau kursus gratis.',
            ],
            'courses' => $courses,
            'filters' => $filters->toArray(),
            'categories' => Category::query()
                ->whereHas('courses', fn (Builder $query) => $query->published())
                ->orderBy('name')
                ->get(['name', 'slug']),
        ]);
    }

    public function show(Course $course, PublicCurriculum $publicCurriculum): Response
    {
        $course->load([
            'category:id,name,slug',
            'instructors:users.id,users.name,users.avatar_path',
            'instructors.instructorProfile:id,user_id,headline,bio',
        ]);

        return Inertia::render('public/courses/show', [
            'meta' => [
                'title' => $course->title,
                'description' => Str::limit((string) ($course->summary ?: $course->description), 160),
            ],
            'course' => CourseDetailResource::make($course)->resolve(),
            // Published lessons only; video data only for preview lessons (see PublicCurriculum).
            'curriculum' => $publicCurriculum->handle($course),
        ]);
    }
}
