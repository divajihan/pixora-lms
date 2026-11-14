<?php

namespace App\Services\Images;

/**
 * Re-encoded image bytes (no metadata) plus a thumbnail.
 */
final readonly class NormalizedImage
{
    public function __construct(
        public string $contents,
        public string $mime,
        public string $extension,
        public int $width,
        public int $height,
        public string $thumbnail,
    ) {}
}
