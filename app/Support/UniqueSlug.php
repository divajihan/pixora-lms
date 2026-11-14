<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Str;

/**
 * Generates a slug that is not taken yet ("judul", "judul-2", "judul-3", ...).
 */
class UniqueSlug
{
    /**
     * @param  Closure(string): bool  $exists  Whether a slug is already used (include soft-deleted rows).
     */
    public static function make(string $source, Closure $exists, int $maxLength = 180): string
    {
        $base = Str::limit(Str::slug($source), $maxLength, '') ?: 'item';
        $slug = $base;
        $suffix = 2;

        while ($exists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
