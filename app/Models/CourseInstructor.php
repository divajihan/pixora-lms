<?php

namespace App\Models;

use App\Enums\CourseInstructorRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $course_id
 * @property int $user_id
 * @property CourseInstructorRole $role
 */
class CourseInstructor extends Pivot
{
    protected $table = 'course_instructors';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CourseInstructorRole::class,
        ];
    }
}
