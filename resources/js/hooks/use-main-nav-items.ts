import { usePage } from '@inertiajs/react';
import { BookOpen, Globe, LayoutGrid, Library, Users } from 'lucide-react';
import { dashboardFor } from '@/lib/roles';
import { index as adminCourses } from '@/routes/admin/courses';
import { index as adminUsers } from '@/routes/admin/users';
import { index as catalog } from '@/routes/courses';
import { index as instructorCourses } from '@/routes/instructor/courses';
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

    items.push({ title: 'Katalog publik', href: catalog(), icon: Globe });

    return items;
}
