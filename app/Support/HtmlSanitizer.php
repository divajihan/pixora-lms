<?php

namespace App\Support;

use Stevebauman\Purify\Facades\Purify;

/**
 * Server-side HTML sanitising for rich text (config/purify.php "rich_text").
 * Always sanitise before saving; the frontend renders stored HTML as-is.
 *
 * Images: the allowlist keeps <img src alt>, then every image whose src is
 * not one of our own content uploads (/storage/content-images/<random>.<ext>,
 * see ContentImageController) is removed — no external, data: or tracking
 * images.
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

        $clean = trim(self::keepOwnImages($result));

        // Treat editor output with no visible text or image (e.g. "<p></p>") as empty.
        return trim(strip_tags($clean)) === '' && ! str_contains($clean, '<hr') && ! str_contains($clean, '<img')
            ? null
            : $clean;
    }

    public static function contentImagePattern(): string
    {
        $base = preg_quote(rtrim((string) config('filesystems.disks.public.url'), '/'), '#');
        $directory = preg_quote((string) config('pixora.content_images.directory'), '#');

        return "#^{$base}/{$directory}/[A-Za-z0-9]{40}\\.(?:jpg|png|webp)$#";
    }

    private static function keepOwnImages(string $html): string
    {
        $pattern = self::contentImagePattern();

        return (string) preg_replace_callback('/<img\b[^>]*>/i', function (array $match) use ($pattern): string {
            preg_match('/\bsrc="([^"]*)"/i', $match[0], $src);

            if (! isset($src[1]) || preg_match($pattern, html_entity_decode($src[1])) !== 1) {
                return '';
            }

            preg_match('/\balt="([^"]*)"/i', $match[0], $alt);

            return '<img src="'.$src[1].'" alt="'.($alt[1] ?? '').'" />';
        }, $html);
    }
}
