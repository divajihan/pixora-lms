<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Module;
use App\Models\User;

/**
 * Curriculum modules. Only content managers (courses.manage) may change them;
 * instructors read them through CoursePolicy::viewCurriculum.
 */
class ModulePolicy
{
    public function view(User $user, Module $module): bool
    {
        return (new CoursePolicy)->viewCurriculum($user, $module->course)->allowed();
    }

    public function update(User $user, Module $module): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function delete(User $user, Module $module): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    /**
     * Adding a lesson to this module.
     */
    public function addLesson(User $user, Module $module): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }
}
