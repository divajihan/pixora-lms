<?php

namespace App\Enums;

/**
 * Application permissions (spatie/laravel-permission permission names).
 *
 * Course-scoped rules (e.g. "only courses assigned to this instructor") are
 * enforced in Policies on top of these permissions.
 */
enum Permission: string
{
    // Course content: create/edit courses, modules, lessons (Phase 2).
    case CoursesManage = 'courses.manage';
    // Teach assigned courses: lesson unlocks, view students & progress (Phase 4).
    case CoursesTeach = 'courses.teach';
    // Buy or take courses (Phase 3).
    case CoursesEnroll = 'courses.enroll';
    // Review submissions of assigned courses and give feedback (Phase 5).
    case AssignmentsReview = 'assignments.review';
    // See every submission and comment on it (staff, admin) — Phase 5.
    case AssignmentsComment = 'assignments.comment';
    // Orders and manual payment confirmation (Phase 3).
    case OrdersManage = 'orders.manage';
    case PaymentsConfirm = 'payments.confirm';
    // User administration.
    case UsersManage = 'users.manage';
    case UsersView = 'users.view';
    // Help students (e.g. view their enrollments/orders).
    case StudentsAssist = 'students.assist';
    // Moderation (discussions/reviews, stage 2).
    case ContentModerate = 'content.moderate';
    // Reports and application settings (branding, certificate).
    case ReportsView = 'reports.view';
    case SettingsManage = 'settings.manage';
}
