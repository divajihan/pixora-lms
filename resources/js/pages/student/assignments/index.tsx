import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Lock } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { Badge } from '@/components/ui/badge';
import { formatDateTime, formatUnlock } from '@/lib/format';
import { cn } from '@/lib/utils';
import { finalProject, lesson as lessonRoute } from '@/routes/learn';
import { dashboard } from '@/routes/student';
import { index } from '@/routes/student/assignments';
import type { StudentState } from '@/types/assignments';

type Row = {
    id: number;
    title: string;
    is_final_project: boolean;
    course: { title: string; slug: string };
    lesson: { title: string; slug: string } | null;
    opens_at: string | null;
    due_at: string;
    is_extended: boolean;
    has_passed: boolean;
    state: StudentState;
    state_label: string;
    submitted_at: string | null;
    has_new_feedback: boolean;
};

const stateVariant: Record<
    StudentState,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    not_submitted: 'outline',
    on_time: 'secondary',
    late: 'destructive',
    feedback: 'default',
};

export default function StudentAssignments({
    assignments,
}: {
    assignments: Row[];
}) {
    return (
        <>
            <Head title="Tugas saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Tugas saya
                    </h1>
                    <p className="text-muted-foreground">
                        Tugas dari kursus Anda, tenggat (WIB), dan feedback
                        instruktur.
                    </p>
                </div>

                {assignments.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Belum ada tugas"
                        description="Tugas akan muncul saat lesson-nya terbuka."
                    />
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {assignments.map((row) => {
                            const locked = row.opens_at !== null;
                            const href = row.is_final_project
                                ? finalProject(row.course.slug)
                                : row.lesson
                                  ? lessonRoute([
                                        row.course.slug,
                                        row.lesson.slug,
                                    ])
                                  : null;
                            const body = (
                                <>
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className={cn(
                                                'font-medium',
                                                locked &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            {row.title}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {row.course.title} ·{' '}
                                            {row.is_final_project
                                                ? 'Proyek akhir'
                                                : row.lesson?.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {locked ? (
                                                <span className="inline-flex items-center gap-1">
                                                    <Lock className="size-3" />{' '}
                                                    Terbuka{' '}
                                                    {formatUnlock(
                                                        row.opens_at as string,
                                                    )}
                                                </span>
                                            ) : (
                                                <>
                                                    Tenggat{' '}
                                                    {formatDateTime(row.due_at)}{' '}
                                                    WIB
                                                    {row.is_extended &&
                                                        ' (diperpanjang)'}
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-1">
                                        {row.has_new_feedback && (
                                            <Badge>Feedback baru</Badge>
                                        )}
                                        {!locked && (
                                            <Badge
                                                variant={
                                                    stateVariant[row.state]
                                                }
                                            >
                                                {row.state_label}
                                            </Badge>
                                        )}
                                    </div>
                                </>
                            );

                            return (
                                <li key={row.id}>
                                    {locked || !href ? (
                                        <div className="flex items-start gap-3 p-4">
                                            {body}
                                        </div>
                                    ) : (
                                        <Link
                                            href={href}
                                            className="flex items-start gap-3 p-4 hover:bg-accent/50"
                                        >
                                            {body}
                                        </Link>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </>
    );
}

StudentAssignments.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Tugas saya', href: index() },
    ],
};
