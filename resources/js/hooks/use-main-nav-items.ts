import { usePage } from '@inertiajs/react';
import { BookOpen, LayoutGrid } from 'lucide-react';
import { dashboardFor } from '@/lib/roles';
import { index as catalog } from '@/routes/courses';
import type { NavItem } from '@/types';

/**
 * Main navigation for the signed-in app shell (sidebar + header).
 * Role-specific items are added in later phases; links are UI only,
 * access is enforced by route middleware and policies.
 */
export function useMainNavItems(): NavItem[] {
    const { auth } = usePage().props;

    return [
        {
            title: 'Dasbor',
            href: dashboardFor(auth.roles),
            icon: LayoutGrid,
        },
        {
            title: 'Katalog kursus',
            href: catalog(),
            icon: BookOpen,
        },
    ];
}
