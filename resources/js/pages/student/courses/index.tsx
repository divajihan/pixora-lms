import { Head, Link } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { CourseThumbnail } from '@/components/catalog/course-thumbnail';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { index as catalog, show as courseShow } from '@/routes/courses';
import { dashboard } from '@/routes/student';
import { index as myCourses, learn } from '@/routes/student/courses';

type EnrollmentItem = {
    id: number;
    status: string;
    status_label: string;
    is_accessible: boolean;
    enrolled_at: string;
    expires_at: string | null;
    progress_percent: number;
    course: {
        title: string;
        slug: string;
        thumbnail_url: string | null;
        category: string | null;
    };
};

export default function StudentCourses({
    enrollments,
}: {
    enrollments: EnrollmentItem[];
}) {
    const active = enrollments.filter((enrollment) => enrollment.is_accessible);
    const inactive = enrollments.filter(
        (enrollment) => !enrollment.is_accessible,
    );

    return (
        <>
            <Head title="Kursus saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Kursus saya
                    </h1>
                    <p className="text-muted-foreground">
                        Kursus yang sedang Anda ikuti.
                    </p>
                </div>

                {active.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title="Belum ada kursus aktif"
                        description="Beli atau ambil kursus gratis dari katalog untuk mulai belajar."
                        action={
                            <Button asChild size="sm">
                                <Link href={catalog()}>Jelajahi katalog</Link>
                            </Button>
                        }
                    />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {active.map((enrollment) => (
                            <li
                                key={enrollment.id}
                                className="flex flex-col overflow-hidden rounded-lg border bg-card"
                            >
                                <CourseThumbnail
                                    src={enrollment.course.thumbnail_url}
                                    title={enrollment.course.title}
                                    className="border-b"
                                />
                                <div className="flex flex-1 flex-col gap-2 p-4">
                                    {enrollment.course.category && (
                                        <span className="text-xs font-medium tracking-wide text-primary uppercase">
                                            {enrollment.course.category}
                                        </span>
                                    )}
                                    <p className="font-semibold">
                                        {enrollment.course.title}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {enrollment.expires_at
                                            ? `Akses hingga ${formatDate(enrollment.expires_at)}`
                                            : 'Akses seumur hidup'}
                                    </p>
                                    <Button
                                        asChild
                                        size="sm"
                                        className="mt-auto self-start"
                                    >
                                        <Link
                                            href={learn(enrollment.course.slug)}
                                        >
                                            Lanjut belajar
                                        </Link>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {inactive.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">Akses berakhir</h2>
                        <ul className="divide-y rounded-lg border">
                            {inactive.map((enrollment) => (
                                <li
                                    key={enrollment.id}
                                    className="flex items-center justify-between gap-3 p-3 text-sm"
                                >
                                    <Link
                                        href={courseShow(
                                            enrollment.course.slug,
                                        )}
                                        className="truncate hover:underline"
                                    >
                                        {enrollment.course.title}
                                    </Link>
                                    <Badge variant="outline">
                                        {enrollment.status_label}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

StudentCourses.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus saya', href: myCourses() },
    ],
};
