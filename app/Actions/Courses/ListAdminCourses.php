<?php

namespace App\Actions\Courses;

use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Admin course list with title search, status and category filters and
 * module/lesson counts (counts exclude soft-deleted rows).
 */
class ListAdminCourses
{
    public const PER_PAGE = 15;

    /**
     * @return LengthAwarePaginator<int, Course>
     */
    public function handle(?string $search, ?CourseStatus $status, ?int $categoryId): LengthAwarePaginator
    {
        return Course::query()
            ->with(['category:id,name', 'instructors:users.id,users.name'])
            ->withCount(['modules', 'lessons'])
            ->when($search, fn (Builder $query, string $search) => $query
                ->whereRaw("title LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']))
            ->when($status, fn (Builder $query, CourseStatus $status) => $query->where('status', $status))
            ->when($categoryId, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
