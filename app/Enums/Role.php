<?php

namespace App\Enums;

/**
 * Application roles (spatie/laravel-permission role names).
 */
enum Role: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Instructor = 'instructor';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Staff => 'Staf',
            self::Instructor => 'Instruktur',
            self::Student => 'Siswa',
        };
    }

    /**
     * Permissions granted to this role. Admin receives every permission.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Staff => [
                Permission::OrdersManage,
                Permission::PaymentsConfirm,
                Permission::UsersView,
                Permission::StudentsAssist,
                Permission::ContentModerate,
            ],
            self::Instructor => [
                Permission::CoursesTeach,
                Permission::AssignmentsReview,
            ],
            self::Student => [
                Permission::CoursesEnroll,
            ],
        };
    }

    /**
     * Name of the role-specific dashboard route. Staff shares the admin dashboard.
     */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Admin, self::Staff => 'admin.dashboard',
            self::Instructor => 'instructor.dashboard',
            self::Student => 'student.dashboard',
        };
    }
}
