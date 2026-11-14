import { Head, Link, router } from '@inertiajs/react';
import { Inbox, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
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
import { index, show } from '@/routes/submissions';
import type { Paginated } from '@/types/catalog';
import type { ReviewState } from '@/types/assignments';

type Row = {
    id: number;
    student: string;
    email: string;
    assignment: string;
    course: string | null;
    batch: string | null;
    submitted_at: string;
    due_at: string;
    state: ReviewState;
    state_label: string;
    comments_count: number;
};

type Props = {
    submissions: Paginated<Row>;
    courses: { id: number; title: string }[];
    filters: { kursus: number | null; status: string | null; q: string | null };
};

const statuses = [
    { value: 'submitted', label: 'Belum direview' },
    { value: 'reviewed', label: 'Sudah direview' },
];

/**
 * Staff/admin: every submission of every course (read + comment).
 */
export default function SubmissionsIndex({
    submissions,
    courses,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.q ?? '');

    const reload = (params: Record<string, string | number | null>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...params }).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );
        router.get(index().url, query, { preserveState: true, replace: true });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        reload({ q: search.trim() || null });
    };

    return (
        <>
            <Head title="Pengumpulan tugas" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pengumpulan tugas
                    </h1>
                    <p className="text-muted-foreground">
                        Semua pengumpulan dari semua kursus. Komentar staf
                        terlihat oleh siswa.
                    </p>
                </div>

                <div className="flex flex-col gap-2 lg:flex-row">
                    <form
                        role="search"
                        onSubmit={submit}
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
                    </form>
                    <Select
                        value={
                            filters.kursus ? String(filters.kursus) : 'semua'
                        }
                        onValueChange={(value) =>
                            reload({ kursus: value === 'semua' ? null : value })
                        }
                    >
                        <SelectTrigger
                            className="w-full lg:w-64"
                            aria-label="Filter kursus"
                        >
                            <SelectValue>
                                {courses.find(
                                    (course) => course.id === filters.kursus,
                                )?.title ?? 'Semua kursus'}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua kursus</SelectItem>
                            {courses.map((course) => (
                                <SelectItem
                                    key={course.id}
                                    value={String(course.id)}
                                >
                                    {course.title}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status ?? 'semua'}
                        onValueChange={(value) =>
                            reload({ status: value === 'semua' ? null : value })
                        }
                    >
                        <SelectTrigger
                            className="w-full lg:w-48"
                            aria-label="Filter status review"
                        >
                            <SelectValue>
                                {statuses.find(
                                    (status) => status.value === filters.status,
                                )?.label ?? 'Semua status'}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua status</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {submissions.data.length === 0 ? (
                    <EmptyState icon={Inbox} title="Belum ada pengumpulan" />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[760px] text-sm">
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
                                        Tugas
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
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {submissions.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="hover:bg-accent/40"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                href={show(row.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {row.student}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {row.email}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <p>{row.assignment}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {row.course}
                                                {row.batch && ` · ${row.batch}`}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            {formatDateTime(row.submitted_at)}{' '}
                                            WIB
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    row.state === 'late'
                                                        ? 'destructive'
                                                        : row.state ===
                                                            'needs_review'
                                                          ? 'default'
                                                          : 'secondary'
                                                }
                                            >
                                                {row.state_label}
                                            </Badge>
                                            {row.comments_count > 0 && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {row.comments_count}{' '}
                                                    komentar
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={submissions.links} />
            </div>
        </>
    );
}

SubmissionsIndex.layout = {
    breadcrumbs: [{ title: 'Pengumpulan tugas', href: index() }],
};
