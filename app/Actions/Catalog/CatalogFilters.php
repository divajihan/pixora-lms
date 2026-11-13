<?php

namespace App\Actions\Catalog;

use App\Enums\CoursePriceFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sanitised catalog query parameters. Unknown or malformed values are ignored
 * (a public GET page should not answer with validation errors).
 */
final readonly class CatalogFilters
{
    public function __construct(
        public ?string $search = null,
        public ?string $category = null,
        public ?CoursePriceFilter $price = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $search = Str::of((string) $request->query('q', ''))->squish()->limit(100, '')->toString();
        $category = Str::of((string) $request->query('kategori', ''))->trim()->limit(100, '')->toString();

        return new self(
            search: $search !== '' ? $search : null,
            category: $category !== '' ? $category : null,
            price: CoursePriceFilter::tryFrom((string) $request->query('harga', '')),
        );
    }

    /**
     * @return array{q: string|null, kategori: string|null, harga: string|null}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->search,
            'kategori' => $this->category,
            'harga' => $this->price?->value,
        ];
    }
}
