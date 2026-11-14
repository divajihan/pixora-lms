import { usePage } from '@inertiajs/react';
import {
    BookOpen,
    ClipboardCheck,
    ClipboardList,
    Globe,
    LayoutGrid,
    Library,
    Receipt,
    Users,
} from 'lucide-react';
import { dashboardFor } from '@/lib/roles';
import { index as adminCourses } from '@/routes/admin/courses';
import { index as adminOrders } from '@/routes/admin/orders';
import { index as adminUsers } from '@/routes/admin/users';
import { index as catalog } from '@/routes/courses';
import { index as instructorCourses } from '@/routes/instructor/courses';
import { index as submissionsIndex } from '@/routes/submissions';
import { index as studentAssignments } from '@/routes/student/assignments';
import { index as studentCourses } from '@/routes/student/courses';
import { index as studentOrders } from '@/routes/student/orders';
import type { NavItem } from '@/types';

/**
 * Main navigation for the signed-in app shell (sidebar + header), based on the
 * user's roles/permissions. UI only: routes are protected by middleware and policies.
 */
export function useMainNavItems(): NavItem[] {
    const { auth } = usePage().props;
    const can = (permission: string) => auth.permissions.includes(permission);

    const items: NavItem[] = [
        { title: 'Dasbor', href: dashboardFor(auth.roles), icon: LayoutGrid },
    ];

    if (can('courses.manage')) {
        items.push({ title: 'Kursus', href: adminCourses(), icon: Library });
    }

    if (can('orders.manage')) {
        items.push({ title: 'Pesanan', href: adminOrders(), icon: Receipt });
    }

    if (can('assignments.comment')) {
        items.push({
            title: 'Pengumpulan',
            href: submissionsIndex(),
            icon: ClipboardCheck,
        });
    }

    if (can('users.manage')) {
        items.push({ title: 'Pengguna', href: adminUsers(), icon: Users });
    }

    if (auth.roles.includes('instructor')) {
        items.push({
            title: 'Kursus saya',
            href: instructorCourses(),
            icon: BookOpen,
        });
    }

    if (auth.roles.includes('student')) {
        items.push({
            title: 'Kursus saya',
            href: studentCourses(),
            icon: BookOpen,
        });
        items.push({
            title: 'Tugas saya',
            href: studentAssignments(),
            icon: ClipboardList,
        });
        items.push({ title: 'Pesanan', href: studentOrders(), icon: Receipt });
    }

    items.push({ title: 'Katalog publik', href: catalog(), icon: Globe });

    return items;
}
