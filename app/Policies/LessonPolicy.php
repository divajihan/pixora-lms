<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Lesson;
use App\Models\User;

/**
 * Lessons. Only content managers (courses.manage) may change them.
 * Student access (unlock/drip) is added in Phase 4.
 */
class LessonPolicy
{
    public function view(User $user, Lesson $lesson): bool
    {
        return (new CoursePolicy)->viewCurriculum($user, $lesson->course)->allowed();
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }
}
