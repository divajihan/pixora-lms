<?php

namespace App\Enums;

/**
 * Snapshot of the commenter's role when the comment was written (shown to the student).
 */
enum CommentAuthorRole: string
{
    case Instructor = 'instructor';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Instructor => 'Instruktur',
            self::Staff => 'Staf',
            self::Admin => 'Admin',
        };
    }
}
