<?php

namespace App\Actions\Catalog;

use App\Enums\CoursePriceFilter;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public catalog listing: published courses filtered by title, category and price.
 */
class SearchCourses
{
    public const PER_PAGE = 9;

    /**
     * @return LengthAwarePaginator<int, Course>
     */
    public function handle(CatalogFilters $filters, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return Course::query()
            ->published()
            ->withCardRelations()
            ->when($filters->search, fn (Builder $query, string $search) => $query
                ->whereRaw("title LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($search).'%']))
            ->when($filters->category, fn (Builder $query, string $slug) => $query
                ->whereRelation('category', 'slug', $slug))
            ->when($filters->price, fn (Builder $query, CoursePriceFilter $price) => $query
                ->where('is_free', $price === CoursePriceFilter::Free))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Escapes LIKE wildcards with an explicit ESCAPE character, which behaves
     * the same on MySQL and SQLite (backslash is only MySQL's default).
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
