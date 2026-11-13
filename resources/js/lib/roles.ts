import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard as instructorDashboard } from '@/routes/instructor';
import { dashboard as studentDashboard } from '@/routes/student';
import type { RoleName } from '@/types';

export const roleLabels: Record<RoleName, string> = {
    admin: 'Admin',
    staff: 'Staf',
    instructor: 'Instruktur',
    student: 'Siswa',
};

/**
 * Dashboard for the user's highest-privilege role (mirrors App\Enums\Role::dashboardRoute()).
 * Falls back to the student dashboard; the server still enforces access.
 */
export function dashboardFor(roles: RoleName[]) {
    if (roles.includes('admin') || roles.includes('staff')) {
        return adminDashboard();
    }

    if (roles.includes('instructor')) {
        return instructorDashboard();
    }

    return studentDashboard();
}
