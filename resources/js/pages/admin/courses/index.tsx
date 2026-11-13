import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, ListTree, Pencil, Plus, Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { StatusBadge } from '@/components/admin/status-badge';
import { Pagination } from '@/components/catalog/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRupiah } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import {
    create,
    curriculum,
    edit,
    index as coursesIndex,
} from '@/routes/admin/courses';
import type { AdminCourse, CategoryItem, Option } from '@/types/admin';
import type { Paginated } from '@/types/catalog';

type Filters = {
    q: string | null;
    status: string | null;
    kategori: number | null;
};

const ALL = 'semua';

export default function AdminCourseIndex({
    courses,
    filters,
    categories,
    statuses,
}: {
    courses: Paginated<AdminCourse>;
    filters: Filters;
    categories: CategoryItem[];
    statuses: Option[];
}) {
    const [search, setSearch] = useState(filters.q ?? '');
    const [loading, setLoading] = useState(false);

    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, q: search.trim() || null, ...next };
        const query = Object.fromEntries(
            Object.entries(merged).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );

        router.get(coursesIndex().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    const hasFilters = Boolean(filters.q || filters.status || filters.kategori);
    const statusLabel =
        statuses.find((item) => item.value === filters.status)?.label ??
        'Semua status';
    const categoryLabel =
        categories.find((item) => item.id === filters.kategori)?.name ??
        'Semua kategori';

    return (
        <>
            <Head title="Kursus" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Kursus
                        </h1>
                        <p className="text-muted-foreground">
                            Kelola kursus, kurikulum, dan status terbit.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            Kursus baru
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 lg:flex-row">
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
                                placeholder="Cari judul kursus…"
                                aria-label="Cari judul kursus"
                                className="pl-9"
                                maxLength={100}
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            Cari
                        </Button>
                    </form>
                    <div className="grid grid-cols-2 gap-2 lg:flex">
                        <Select
                            value={filters.status ?? ALL}
                            onValueChange={(value) =>
                                apply({ status: value === ALL ? null : value })
                            }
                        >
                            <SelectTrigger
                                aria-label="Filter status"
                                className="w-full lg:w-44"
                            >
                                <SelectValue>{statusLabel}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Semua status
                                </SelectItem>
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
                        <Select
                            value={
                                filters.kategori
                                    ? String(filters.kategori)
                                    : ALL
                            }
                            onValueChange={(value) =>
                                apply({
                                    kategori:
                                        value === ALL ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger
                                aria-label="Filter kategori"
                                className="w-full lg:w-52"
                            >
                                <SelectValue>{categoryLabel}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Semua kategori
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                    <span aria-live="polite">
                        {loading
                            ? 'Memuat…'
                            : `${courses.total} kursus${hasFilters ? ' cocok dengan filter' : ''}`}
                    </span>
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    coursesIndex().url,
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <X />
                            Hapus filter
                        </Button>
                    )}
                </div>

                {courses.data.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title={
                            hasFilters
                                ? 'Tidak ada kursus yang cocok'
                                : 'Belum ada kursus'
                        }
                        description={
                            hasFilters
                                ? 'Coba kata kunci atau filter lain.'
                                : 'Buat kursus pertama Anda, lalu susun kurikulumnya.'
                        }
                        action={
                            !hasFilters && (
                                <Button asChild size="sm">
                                    <Link href={create()}>
                                        <Plus />
                                        Kursus baru
                                    </Link>
                                </Button>
                            )
                        }
                    />
                ) : (
                    <div
                        className={`overflow-x-auto rounded-lg border transition-opacity ${loading ? 'opacity-60' : ''}`}
                    >
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Kursus
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
                                        Kurikulum
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Harga
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
                                {courses.data.map((course) => (
                                    <tr key={course.id} className="align-top">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={edit(course.id)}
                                                className="font-medium hover:text-primary hover:underline"
                                            >
                                                {course.title}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {course.category ??
                                                    'Tanpa kategori'}
                                                {course.instructors &&
                                                course.instructors.length > 0
                                                    ? ` · ${course.instructors.join(', ')}`
                                                    : ' · Belum ada instruktur'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                status={course.status}
                                            />
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                            {course.modules_count ?? 0} modul ·{' '}
                                            {course.lessons_count ?? 0} lesson
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {course.is_free
                                                ? 'Gratis'
                                                : formatRupiah(course.price)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={curriculum(
                                                            course.id,
                                                        )}
                                                    >
                                                        <ListTree />
                                                        Kurikulum
                                                    </Link>
                                                </Button>
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={edit(course.id)}
                                                    >
                                                        <Pencil />
                                                        Ubah
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={courses.links} />
            </div>
        </>
    );
}

AdminCourseIndex.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus', href: coursesIndex() },
    ],
};
