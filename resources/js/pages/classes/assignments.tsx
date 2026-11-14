import { Head, Link } from '@inertiajs/react';
import { CalendarClock, ClipboardList } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { BatchSelect } from '@/components/assignments/batch-select';
import { DeadlineDialog } from '@/components/assignments/deadline-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/classes/assignments';
import type { AssignmentOverview, BatchOptionLite } from '@/types/assignments';

type Props = {
    course: { id: number; title: string; slug: string; drip_enabled: boolean };
    batches: BatchOptionLite[];
    selectedBatchId: number | null;
    assignments: AssignmentOverview[];
    canManageDeadlines: boolean;
};

function Count({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: 'warn' | 'bad';
}) {
    return (
        <div className="rounded-md border px-2 py-1 text-center">
            <p
                className={
                    tone === 'warn'
                        ? 'font-semibold text-primary'
                        : tone === 'bad'
                          ? 'font-semibold text-destructive'
                          : 'font-semibold'
                }
            >
                {value}
            </p>
            <p className="text-[11px] leading-tight text-muted-foreground">
                {label}
            </p>
        </div>
    );
}

export default function ClassAssignments({
    course,
    batches,
    selectedBatchId,
    assignments,
    canManageDeadlines,
}: Props) {
    const [extending, setExtending] = useState<AssignmentOverview | null>(null);
    const batchQuery = selectedBatchId ? { batch: selectedBatchId } : {};

    return (
        <>
            <Head title={`Tugas: ${course.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Tugas
                        </h1>
                        <p className="text-muted-foreground">
                            {course.title} · feedback diberikan setelah tenggat,
                            hanya untuk pengumpulan tepat waktu.
                        </p>
                    </div>
                    <BatchSelect
                        batches={batches}
                        selectedId={selectedBatchId}
                        url={index(course.id).url}
                    />
                </div>

                {assignments.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Belum ada tugas"
                        description="Admin menambahkan tugas di kurikulum kursus."
                    />
                ) : (
                    <ul className="flex flex-col gap-3">
                        {assignments.map((assignment) => (
                            <li
                                key={assignment.id}
                                className="flex flex-col gap-3 rounded-lg border bg-card p-4 lg:flex-row lg:items-center"
                            >
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={show(
                                                {
                                                    course: course.id,
                                                    assignment: assignment.id,
                                                },
                                                { query: batchQuery },
                                            )}
                                            className="font-semibold hover:underline"
                                        >
                                            {assignment.title}
                                        </Link>
                                        {assignment.is_final_project && (
                                            <Badge variant="secondary">
                                                Proyek akhir
                                            </Badge>
                                        )}
                                        {assignment.status === 'draft' && (
                                            <Badge
                                                variant="outline"
                                                className="border-dashed"
                                            >
                                                Draf
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {assignment.lesson
                                            ? `Hari ke-${assignment.lesson.day_number} · ${assignment.lesson.title}`
                                            : 'Setelah batch berakhir'}
                                    </p>
                                    <p className="flex flex-wrap items-center gap-1 text-sm">
                                        <CalendarClock className="size-4 text-muted-foreground" />
                                        {assignment.deadline ? (
                                            <>
                                                Tenggat{' '}
                                                {formatDateTime(
                                                    assignment.deadline
                                                        .effective,
                                                )}{' '}
                                                WIB
                                                {assignment.deadline
                                                    .override && (
                                                    <Badge variant="secondary">
                                                        Diperpanjang
                                                    </Badge>
                                                )}
                                                {assignment.deadline
                                                    .has_passed && (
                                                    <span className="text-xs text-muted-foreground">
                                                        (sudah lewat)
                                                    </span>
                                                )}
                                            </>
                                        ) : (
                                            <>
                                                Tenggat{' '}
                                                {assignment.due_offset_days}{' '}
                                                hari setelah siswa terdaftar
                                            </>
                                        )}
                                    </p>
                                </div>
                                <div className="grid grid-cols-4 gap-2 sm:w-96">
                                    <Count
                                        label="Belum mengumpulkan"
                                        value={assignment.counts.not_submitted}
                                    />
                                    <Count
                                        label="Tepat waktu, belum direview"
                                        value={
                                            assignment.counts.awaiting_review
                                        }
                                        tone={
                                            assignment.counts.needs_review > 0
                                                ? 'warn'
                                                : undefined
                                        }
                                    />
                                    <Count
                                        label="Sudah direview"
                                        value={assignment.counts.reviewed}
                                    />
                                    <Count
                                        label="Terlambat"
                                        value={assignment.counts.late}
                                        tone={
                                            assignment.counts.late > 0
                                                ? 'bad'
                                                : undefined
                                        }
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button asChild size="sm">
                                        <Link
                                            href={show(
                                                {
                                                    course: course.id,
                                                    assignment: assignment.id,
                                                },
                                                { query: batchQuery },
                                            )}
                                        >
                                            Lihat siswa
                                        </Link>
                                    </Button>
                                    {canManageDeadlines &&
                                        assignment.deadline &&
                                        selectedBatchId && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setExtending(assignment)
                                                }
                                            >
                                                Perpanjang
                                            </Button>
                                        )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {extending?.deadline && selectedBatchId && (
                <DeadlineDialog
                    open
                    onOpenChange={(open) => !open && setExtending(null)}
                    courseId={course.id}
                    assignmentId={extending.id}
                    scope={{ batch_id: selectedBatchId }}
                    title={`${extending.title} — seluruh batch`}
                    current={extending.deadline.effective}
                    hasOverride={extending.deadline.override !== null}
                />
            )}
        </>
    );
}

ClassAssignments.layout = (props: Props) => ({
    breadcrumbs: [
        { title: props.course.title, href: index(props.course.id) },
        { title: 'Tugas', href: index(props.course.id) },
    ],
});
