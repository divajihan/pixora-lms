<?php

namespace App\Enums;

enum LessonType: string
{
    case Video = 'video';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Live => 'Kelas live',
        };
    }
}
