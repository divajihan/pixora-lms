import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarRange,
    ClipboardCheck,
    Users,
} from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/format';
import { batches as batchesRoute, schedule, students } from '@/routes/classes';
import { index as assignmentsIndex } from '@/routes/classes/assignments';
import type { BatchData } from '@/types/learning';

type Props = {
    course: { id: number; title: string; slug: string; drip_enabled: boolean };
    batches: BatchData[];
};

export default function ClassBatches({ course, batches }: Props) {
    return (
        <>
            <Head title={`Kelas: ${course.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {course.title}
                    </h1>
                    <p className="text-muted-foreground">
                        Pilih batch untuk melihat siswa dan mengatur jadwal
                        pembukaan lesson.
                    </p>
                </div>

                {batches.length === 0 ? (
                    <EmptyState
                        icon={CalendarRange}
                        title="Belum ada batch"
                        description="Admin belum membuat batch untuk kursus ini."
                    />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {batches.map((batch) => (
                            <li
                                key={batch.id}
                                className="flex flex-col gap-3 rounded-lg border bg-card p-4"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold">
                                            {batch.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDateRange(
                                                batch.starts_on,
                                                batch.ends_on,
                                            )}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            batch.status === 'running'
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        {batch.status_label}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {batch.seats_taken} siswa
                                    {batch.capacity
                                        ? ` dari kuota ${batch.capacity}`
                                        : ''}
                                    {batch.over_capacity && (
                                        <span className="text-destructive">
                                            {' '}
                                            (melebihi kuota)
                                        </span>
                                    )}
                                </p>
                                <div className="mt-auto flex flex-wrap gap-2">
                                    <Button asChild size="sm">
                                        <Link
                                            href={students([
                                                course.id,
                                                batch.id,
                                            ])}
                                        >
                                            <Users />
                                            Siswa
                                        </Link>
                                    </Button>
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={schedule([
                                                course.id,
                                                batch.id,
                                            ])}
                                        >
                                            <CalendarClock />
                                            Jadwal lesson
                                        </Link>
                                    </Button>
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={assignmentsIndex(course.id, {
                                                query: { batch: batch.id },
                                            })}
                                        >
                                            <ClipboardCheck />
                                            Tugas
                                        </Link>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ClassBatches.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Kelas', href: batchesRoute(props.course.id) },
        { title: props.course.title, href: batchesRoute(props.course.id) },
    ],
});
