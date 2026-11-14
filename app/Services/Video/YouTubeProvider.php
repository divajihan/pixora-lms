<?php

namespace App\Services\Video;

/**
 * YouTube (unlisted) videos. Accepts watch, youtu.be, embed, shorts, live and
 * /v/ URLs (www., m., music. and youtube-nocookie.com hosts) or a bare 11-char id.
 */
class YouTubeProvider implements VideoProvider
{
    private const ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    private const HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    public function key(): string
    {
        return 'youtube';
    }

    public function parseReference(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (preg_match(self::ID_PATTERN, $input) === 1) {
            return $input;
        }

        if (! preg_match('#^https?://#i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $segments = array_values(array_filter(explode('/', $path), fn (string $segment) => $segment !== ''));

        $candidate = null;

        if ($host === 'youtu.be' || $host === 'www.youtu.be') {
            $candidate = $segments[0] ?? null;
        } elseif (in_array($host, self::HOSTS, true)) {
            if (($segments[0] ?? null) === 'watch') {
                parse_str($parts['query'] ?? '', $query);
                $candidate = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (in_array($segments[0] ?? null, ['embed', 'shorts', 'live', 'v'], true)) {
                $candidate = $segments[1] ?? null;
            }
        }

        return is_string($candidate) && preg_match(self::ID_PATTERN, $candidate) === 1
            ? $candidate
            : null;
    }

    public function embedUrl(string $reference): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.rawurlencode($reference).'?rel=0&modestbranding=1';
    }

    public function thumbnailUrl(string $reference): string
    {
        return 'https://i.ytimg.com/vi/'.rawurlencode($reference).'/hqdefault.jpg';
    }

    public function watchUrl(string $reference): string
    {
        return 'https://www.youtube.com/watch?v='.rawurlencode($reference);
    }
}
