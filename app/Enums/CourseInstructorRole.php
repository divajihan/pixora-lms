<?php

namespace App\Enums;

/**
 * Role of an instructor within a specific course (course_instructors.role).
 */
enum CourseInstructorRole: string
{
    case Lead = 'lead';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Instruktur utama',
            self::Assistant => 'Asisten instruktur',
        };
    }
}
