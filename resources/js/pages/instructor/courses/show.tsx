import { Head, Link } from '@inertiajs/react';
import {
    ClipboardCheck,
    ExternalLink,
    ListTree,
    Lock,
    Users,
} from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { StatusBadge } from '@/components/admin/status-badge';
import { CurriculumReadonly } from '@/components/curriculum-readonly';
import { Button } from '@/components/ui/button';
import { formatDuration } from '@/lib/format';
import { batches as classBatches } from '@/routes/classes';
import { index as classAssignments } from '@/routes/classes/assignments';
import { show as publicShow } from '@/routes/courses';
import { dashboard } from '@/routes/instructor';
import { index as myCourses, show } from '@/routes/instructor/courses';
import type {
    CourseStatusValue,
    CurriculumModule,
    CurriculumTotals,
} from '@/types/admin';

type Props = {
    course: {
        drip_enabled: boolean;
        id: number;
        title: string;
        slug: string;
        status: CourseStatusValue;
        status_label: string;
        is_public: boolean;
    };
    modules: CurriculumModule[];
    totals: CurriculumTotals;
};

export default function InstructorCourseShow({
    course,
    modules,
    totals,
}: Props) {
    return (
        <>
            <Head title={course.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <h1 className="truncate text-2xl font-semibold tracking-tight">
                                {course.title}
                            </h1>
                            <StatusBadge status={course.status} />
                        </div>
                        <p className="text-muted-foreground">
                            {totals.modules} modul · {totals.lessons} lesson (
                            {totals.published_lessons} terbit) ·{' '}
                            {formatDuration(totals.duration_seconds)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {course.drip_enabled && (
                            <Button asChild>
                                <Link href={classBatches(course.id)}>
                                    <Users />
                                    Kelola kelas
                                </Link>
                            </Button>
                        )}
                        <Button
                            asChild
                            variant={
                                course.drip_enabled ? 'outline' : 'default'
                            }
                        >
                            <Link href={classAssignments(course.id)}>
                                <ClipboardCheck />
                                Tugas
                            </Link>
                        </Button>
                        {course.is_public && (
                            <Button asChild variant="outline">
                                <a
                                    href={publicShow(course.slug).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Halaman publik
                                    <ExternalLink />
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                <p className="flex items-center gap-2 rounded-md border bg-muted/40 p-3 text-sm text-muted-foreground">
                    <Lock className="size-4 shrink-0" />
                    Tampilan baca saja. Perubahan konten dilakukan oleh admin.
                </p>

                {modules.length === 0 ? (
                    <EmptyState
                        icon={ListTree}
                        title="Kurikulum belum disusun"
                        description="Admin belum menambahkan modul ke kursus ini."
                    />
                ) : (
                    <CurriculumReadonly modules={modules} />
                )}
            </div>
        </>
    );
}

InstructorCourseShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus saya', href: myCourses() },
        { title: props.course.title, href: show(props.course.id) },
    ],
});
