<?php

namespace App\Enums;

/**
 * Derived from dates (not stored): upcoming before starts_on, running until
 * ends_on (inclusive), finished afterwards. Dates are in the app timezone.
 */
enum BatchStatus: string
{
    case Upcoming = 'upcoming';
    case Running = 'running';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Akan datang',
            self::Running => 'Sedang berjalan',
            self::Finished => 'Selesai',
        };
    }
}
