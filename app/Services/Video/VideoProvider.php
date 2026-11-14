<?php

namespace App\Services\Video;

/**
 * Abstraction over the video host (YouTube unlisted first; Bunny/Vimeo later).
 *
 * Only the provider's video id ("reference") is stored in lessons.video_ref.
 * Security: references must only reach the browser for lessons the viewer may
 * watch (preview lessons now; unlocked lessons from Phase 4).
 */
interface VideoProvider
{
    /**
     * Key stored in lessons.video_provider (e.g. "youtube").
     */
    public function key(): string;

    /**
     * Extracts the video reference from a URL or a bare id. Null when the input
     * is not a valid video of this provider.
     */
    public function parseReference(string $input): ?string;

    /**
     * Privacy-friendly embed URL for an iframe player.
     */
    public function embedUrl(string $reference): string;

    /**
     * Public thumbnail image URL.
     */
    public function thumbnailUrl(string $reference): string;

    /**
     * Canonical watch URL (used to pre-fill admin forms).
     */
    public function watchUrl(string $reference): string;
}
