import { Head, Link, router } from '@inertiajs/react';
import { Search, Users, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { BatchSelect } from '@/components/assignments/batch-select';
import { DeadlineDialog } from '@/components/assignments/deadline-dialog';
import { Pagination } from '@/components/catalog/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/classes/assignments';
import { show as submissionShow } from '@/routes/submissions';
import type { Paginated } from '@/types/catalog';
import type {
    AssignmentOverview,
    BatchOptionLite,
    ReviewState,
} from '@/types/assignments';

type StudentRow = {
    enrollment_id: number;
    name: string;
    email: string;
    batch: string | null;
    review_state: ReviewState;
    review_label: string;
    submission_id: number | null;
    submitted_at: string | null;
    comments_count: number;
    due_at: string;
    deadline_source: 'default' | 'batch' | 'enrollment';
    student_due_override: string | null;
};

type Props = {
    course: { id: number; title: string; slug: string; drip_enabled: boolean };
    batches: BatchOptionLite[];
    selectedBatchId: number | null;
    assignment: AssignmentOverview;
    students: Paginated<StudentRow>;
    filters: { status: string | null; q: string | null };
    statusOptions: { value: string; label: string }[];
    canManageDeadlines: boolean;
};

const variant: Record<
    ReviewState,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    not_submitted: 'outline',
    on_time: 'secondary',
    needs_review: 'default',
    reviewed: 'secondary',
    late: 'destructive',
};

export default function AssignmentStudents(props: Props) {
    const {
        course,
        batches,
        selectedBatchId,
        assignment,
        students,
        filters,
        statusOptions,
        canManageDeadlines,
    } = props;
    const [search, setSearch] = useState(filters.q ?? '');
    const [extending, setExtending] = useState<StudentRow | null>(null);
    const url = show({ course: course.id, assignment: assignment.id }).url;
    const base = selectedBatchId ? { batch: selectedBatchId } : {};

    const reload = (params: Record<string, string | number | null>) => {
        const query = Object.fromEntries(
            Object.entries({
                ...base,
                status: filters.status,
                q: filters.q,
                ...params,
            }).filter(([, value]) => value !== null && value !== ''),
        );
        router.get(url, query, { preserveState: true, replace: true });
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        reload({ q: search.trim() || null });
    };

    return (
        <>
            <Head title={assignment.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {assignment.title}
                        </h1>
                        <p className="text-muted-foreground">
                            {course.title}
                            {assignment.deadline &&
                                ` · tenggat ${formatDateTime(assignment.deadline.effective)} WIB`}
                        </p>
                    </div>
                    <BatchSelect
                        batches={batches}
                        selectedId={selectedBatchId}
                        url={url}
                    />
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <form
                        role="search"
                        onSubmit={submitSearch}
                        className="flex flex-1 gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama atau email siswa…"
                                aria-label="Cari siswa"
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            Cari
                        </Button>
                        {filters.q && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setSearch('');
                                    reload({ q: null });
                                }}
                            >
                                <X />
                                Hapus
                            </Button>
                        )}
                    </form>
                    <Select
                        value={filters.status ?? 'semua'}
                        onValueChange={(value) =>
                            reload({
                                status: value === 'semua' ? null : value,
                                page: null,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-52"
                            aria-label="Filter status"
                        >
                            <SelectValue>
                                {statusOptions.find(
                                    (option) => option.value === filters.status,
                                )?.label ?? 'Semua status'}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua status</SelectItem>
                            {statusOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <p className="text-sm text-muted-foreground">
                    {students.total} siswa
                </p>

                {students.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Tidak ada siswa"
                        description={
                            filters.q || filters.status
                                ? 'Coba ubah filter atau pencarian.'
                                : 'Belum ada siswa di batch ini.'
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Siswa
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Dikumpulkan
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Tenggat
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium"
                                    >
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {students.data.map((row) => (
                                    <tr key={row.enrollment_id}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {row.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {row.email}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    variant[row.review_state]
                                                }
                                            >
                                                {row.review_label}
                                            </Badge>
                                            {row.review_state === 'late' && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Tidak mendapat feedback
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            {row.submitted_at
                                                ? `${formatDateTime(row.submitted_at)} WIB`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            {formatDateTime(row.due_at)} WIB
                                            {row.deadline_source ===
                                                'enrollment' && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-1"
                                                >
                                                    Khusus
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                {canManageDeadlines && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setExtending(row)
                                                        }
                                                    >
                                                        Perpanjang
                                                    </Button>
                                                )}
                                                {row.submission_id && (
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant={
                                                            row.review_state ===
                                                            'needs_review'
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                    >
                                                        <Link
                                                            href={submissionShow(
                                                                row.submission_id,
                                                            )}
                                                        >
                                                            {row.review_state ===
                                                            'needs_review'
                                                                ? 'Review'
                                                                : 'Lihat'}
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={students.links} />
            </div>

            {extending && (
                <DeadlineDialog
                    open
                    onOpenChange={(open) => !open && setExtending(null)}
                    courseId={course.id}
                    assignmentId={assignment.id}
                    scope={{ enrollment_id: extending.enrollment_id }}
                    title={`${assignment.title} — khusus ${extending.name}`}
                    current={extending.due_at}
                    hasOverride={extending.student_due_override !== null}
                />
            )}
        </>
    );
}

AssignmentStudents.layout = (props: Props) => ({
    breadcrumbs: [
        { title: props.course.title, href: index(props.course.id) },
        { title: 'Tugas', href: index(props.course.id) },
        {
            title: props.assignment.title,
            href: show({
                course: props.course.id,
                assignment: props.assignment.id,
            }),
        },
    ],
});
