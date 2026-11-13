<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseCardResource;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public const LATEST_COURSES = 6;

    public function __invoke(): Response
    {
        $courses = Course::query()
            ->published()
            ->withCardRelations()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::LATEST_COURSES)
            ->get();

        $categories = Category::query()
            ->whereHas('courses', fn (Builder $query) => $query->published())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('public/home', [
            'meta' => [
                'title' => null,
                'description' => 'Belajar online di '.config('app.name').': kursus video, rangkuman materi, tugas dengan feedback instruktur, dan sertifikat.',
            ],
            'latestCourses' => CourseCardResource::collection($courses)->resolve(),
            'categories' => $categories->map->only(['name', 'slug'])->values(),
        ]);
    }
}
