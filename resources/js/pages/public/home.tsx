import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Award, ClipboardCheck, PlayCircle } from 'lucide-react';
import { CourseCard } from '@/components/catalog/course-card';
import { SeoHead } from '@/components/seo-head';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';
import { index as catalog } from '@/routes/courses';
import type {
    CategoryOption,
    CourseCard as CourseCardData,
    PageMeta,
} from '@/types/catalog';

const highlights = [
    {
        icon: PlayCircle,
        title: 'Video & rangkuman',
        description: 'Materi video singkat dengan rangkuman tertulis.',
    },
    {
        icon: ClipboardCheck,
        title: 'Tugas dengan feedback',
        description: 'Praktik langsung dan dapat masukan dari instruktur.',
    },
    {
        icon: Award,
        title: 'Sertifikat',
        description: 'Sertifikat otomatis setelah menyelesaikan kursus.',
    },
];

export default function Home({
    meta,
    latestCourses,
    categories,
}: {
    meta: PageMeta;
    latestCourses: CourseCardData[];
    categories: CategoryOption[];
}) {
    const { auth, name } = usePage().props;

    return (
        <>
            <SeoHead title={meta.title} description={meta.description} />

            <section className="border-b bg-gradient-to-b from-primary/5 to-background">
                <div className="mx-auto flex w-full max-w-6xl flex-col items-start gap-6 px-4 py-16 md:px-6 md:py-24">
                    <span className="rounded-full border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                        Belajar online, kapan saja
                    </span>
                    <h1 className="max-w-3xl text-4xl font-semibold tracking-tight text-balance md:text-5xl">
                        Kuasai keterampilan baru bersama {name}
                    </h1>
                    <p className="max-w-2xl text-lg text-muted-foreground">
                        Kursus online praktis dari instruktur berpengalaman.
                        Belajar sesuai jadwal Anda, kerjakan tugas, dan raih
                        sertifikat.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Button asChild size="lg">
                            <Link href={catalog()}>
                                Lihat katalog
                                <ArrowRight />
                            </Link>
                        </Button>
                        {!auth.user && (
                            <Button asChild size="lg" variant="outline">
                                <Link href={register()}>Daftar gratis</Link>
                            </Button>
                        )}
                    </div>
                    {categories.length > 0 && (
                        <div className="flex flex-wrap gap-2 pt-2">
                            {categories.map((category) => (
                                <Link
                                    key={category.slug}
                                    href={catalog({
                                        query: { kategori: category.slug },
                                    })}
                                    className="rounded-full border bg-background px-3 py-1 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                                >
                                    {category.name}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </section>

            <section className="mx-auto w-full max-w-6xl px-4 py-12 md:px-6 md:py-16">
                <div className="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-semibold tracking-tight">
                            Kursus terbaru
                        </h2>
                        <p className="text-muted-foreground">
                            Pilihan kursus yang baru saja terbit.
                        </p>
                    </div>
                    <Link
                        href={catalog()}
                        className="hidden shrink-0 text-sm font-medium text-primary hover:underline sm:inline"
                    >
                        Semua kursus →
                    </Link>
                </div>

                {latestCourses.length > 0 ? (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {latestCourses.map((course) => (
                            <CourseCard key={course.id} course={course} />
                        ))}
                    </div>
                ) : (
                    <p className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                        Belum ada kursus yang terbit. Silakan kembali lagi
                        nanti.
                    </p>
                )}

                <Button asChild variant="outline" className="mt-6 sm:hidden">
                    <Link href={catalog()}>Semua kursus</Link>
                </Button>
            </section>

            <section className="border-t bg-muted/40">
                <div className="mx-auto grid w-full max-w-6xl gap-6 px-4 py-12 sm:grid-cols-3 md:px-6">
                    {highlights.map((item) => (
                        <div key={item.title} className="flex gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <item.icon className="size-5" />
                            </span>
                            <div>
                                <h3 className="font-medium">{item.title}</h3>
                                <p className="text-sm text-muted-foreground">
                                    {item.description}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </>
    );
}
