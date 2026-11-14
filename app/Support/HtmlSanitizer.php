<?php

namespace App\Support;

use Stevebauman\Purify\Facades\Purify;

/**
 * Server-side HTML sanitising for rich text (config/purify.php "rich_text").
 * Always sanitise before saving; the frontend renders stored HTML as-is.
 */
class HtmlSanitizer
{
    public function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $result = Purify::config('rich_text')->clean($html);

        if (! is_string($result)) {
            return null;
        }

        $clean = trim($result);

        // Treat editor output with no visible text (e.g. "<p></p>") as empty.
        return trim(strip_tags($clean)) === '' && ! str_contains($clean, '<hr') ? null : $clean;
    }
}
