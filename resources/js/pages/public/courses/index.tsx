import { Link, router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CourseCard } from '@/components/catalog/course-card';
import { Pagination } from '@/components/catalog/pagination';
import { SeoHead } from '@/components/seo-head';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as catalog } from '@/routes/courses';
import type {
    CatalogFilters,
    CategoryOption,
    CourseCard as CourseCardData,
    PageMeta,
    Paginated,
} from '@/types/catalog';

// Radix Select does not allow an empty-string value, so "all" uses a sentinel.
const ALL = 'semua';

export default function CourseIndex({
    meta,
    courses,
    filters,
    categories,
}: {
    meta: PageMeta;
    courses: Paginated<CourseCardData>;
    filters: CatalogFilters;
    categories: CategoryOption[];
}) {
    const [search, setSearch] = useState(filters.q ?? '');

    const apply = (next: Partial<CatalogFilters>) => {
        const merged = { ...filters, q: search || null, ...next };
        const query = Object.fromEntries(
            Object.entries(merged).filter(([, value]) => value),
        );

        router.get(catalog().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        apply({ q: search.trim() || null });
    };

    const hasFilters = Boolean(filters.q || filters.kategori || filters.harga);

    return (
        <>
            <SeoHead title={meta.title} description={meta.description} />

            <section className="border-b bg-muted/30">
                <div className="mx-auto w-full max-w-6xl px-4 py-10 md:px-6">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Katalog kursus
                    </h1>
                    <p className="mt-1 text-muted-foreground">
                        Temukan kursus yang sesuai dengan tujuan belajar Anda.
                    </p>

                    <div className="mt-6 flex flex-col gap-3 lg:flex-row">
                        <form
                            role="search"
                            onSubmit={submitSearch}
                            className="flex flex-1 gap-2"
                        >
                            <div className="relative flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    name="q"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari judul kursus…"
                                    aria-label="Cari judul kursus"
                                    className="bg-background pl-9"
                                    maxLength={100}
                                />
                            </div>
                            <Button type="submit">Cari</Button>
                        </form>

                        <div className="grid grid-cols-2 gap-2 lg:flex">
                            <Select
                                value={filters.kategori ?? ALL}
                                onValueChange={(value) =>
                                    apply({
                                        kategori: value === ALL ? null : value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    aria-label="Filter kategori"
                                    className="w-full bg-background lg:w-52"
                                >
                                    <SelectValue placeholder="Kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Semua kategori
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.slug}
                                            value={category.slug}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.harga ?? ALL}
                                onValueChange={(value) =>
                                    apply({
                                        harga:
                                            value === ALL
                                                ? null
                                                : (value as CatalogFilters['harga']),
                                    })
                                }
                            >
                                <SelectTrigger
                                    aria-label="Filter harga"
                                    className="w-full bg-background lg:w-40"
                                >
                                    <SelectValue placeholder="Harga" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Semua harga
                                    </SelectItem>
                                    <SelectItem value="gratis">
                                        Gratis
                                    </SelectItem>
                                    <SelectItem value="berbayar">
                                        Berbayar
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto w-full max-w-6xl px-4 py-8 md:px-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                    <p
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {courses.total > 0
                            ? `Menampilkan ${courses.from}–${courses.to} dari ${courses.total} kursus`
                            : 'Tidak ada kursus yang cocok'}
                        {filters.q && (
                            <>
                                {' '}
                                untuk “
                                <span className="text-foreground">
                                    {filters.q}
                                </span>
                                ”
                            </>
                        )}
                    </p>
                    {hasFilters && (
                        <Button asChild variant="ghost" size="sm">
                            <Link
                                href={catalog()}
                                onClick={() => setSearch('')}
                                preserveScroll
                            >
                                <X />
                                Hapus filter
                            </Link>
                        </Button>
                    )}
                </div>

                {courses.data.length > 0 ? (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {courses.data.map((course) => (
                            <CourseCard key={course.id} course={course} />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="font-medium">Kursus tidak ditemukan</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Coba kata kunci lain atau hapus filter.
                        </p>
                    </div>
                )}

                <div className="mt-10">
                    <Pagination links={courses.links} />
                </div>
            </section>
        </>
    );
}
