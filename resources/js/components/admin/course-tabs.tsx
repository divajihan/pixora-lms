import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { index as batchesIndex } from '@/routes/admin/batches';
import { curriculum, edit } from '@/routes/admin/courses';

/**
 * Sub-navigation of an admin course: Info · Kurikulum · Batch (drip only).
 */
export function CourseTabs({
    courseId,
    active,
    dripEnabled,
}: {
    courseId: number;
    active: 'info' | 'curriculum' | 'batches';
    dripEnabled: boolean;
}) {
    const tabs = [
        { key: 'info', label: 'Info kursus', href: edit(courseId) },
        { key: 'curriculum', label: 'Kurikulum', href: curriculum(courseId) },
        ...(dripEnabled
            ? [{ key: 'batches', label: 'Batch', href: batchesIndex(courseId) }]
            : []),
    ] as const;

    return (
        <nav
            aria-label="Bagian kursus"
            className="flex gap-1 overflow-x-auto border-b"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    aria-current={active === tab.key ? 'page' : undefined}
                    className={cn(
                        '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground',
                        active === tab.key
                            ? 'border-primary font-medium text-foreground'
                            : 'border-transparent',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
