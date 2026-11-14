import { Head, Link } from '@inertiajs/react';
import { CalendarDays, PlayCircle, Trophy } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { LessonOutline } from '@/components/learn/lesson-outline';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateRange } from '@/lib/format';
import {
    course as courseRoute,
    finalProject as finalProjectRoute,
    lesson as lessonRoute,
} from '@/routes/learn';
import { dashboard } from '@/routes/student';
import { index as myCourses } from '@/routes/student/courses';
import type {
    LearnCourse,
    LearnEnrollment,
    OutlineModule,
} from '@/types/learning';

type Props = {
    course: LearnCourse;
    enrollment: LearnEnrollment;
    modules: OutlineModule[];
    counts: { total: number; completed: number; unlocked: number };
    continueSlug: string | null;
    finalProject: { title: string; opens_at: string | null } | null;
};

export default function LearnCourse({
    course,
    enrollment,
    modules,
    counts,
    continueSlug,
    finalProject,
}: Props) {
    const nextLocked = modules
        .flatMap((module) => module.lessons)
        .find((lesson) => lesson.status === 'locked');

    return (
        <>
            <Head title={course.title} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 rounded-lg border bg-card p-5">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {course.title}
                        </h1>
                        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                            {enrollment.batch && (
                                <span className="inline-flex items-center gap-1">
                                    <CalendarDays className="size-4" />
                                    {enrollment.batch.name} ·{' '}
                                    {formatDateRange(
                                        enrollment.batch.starts_on,
                                        enrollment.batch.ends_on,
                                    )}{' '}
                                    ({enrollment.batch.status_label})
                                </span>
                            )}
                            <span>
                                {enrollment.expires_at
                                    ? `Akses hingga ${formatDate(enrollment.expires_at)}`
                                    : 'Akses seumur hidup'}
                            </span>
                        </p>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-medium">Progres belajar</span>
                            <span className="text-muted-foreground">
                                {counts.completed}/{counts.total} lesson ·{' '}
                                {enrollment.progress_percent}%
                            </span>
                        </div>
                        <div
                            className="h-2 w-full overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-valuenow={enrollment.progress_percent}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label="Progres belajar"
                        >
                            <div
                                className="h-full rounded-full bg-primary transition-all"
                                style={{
                                    width: `${enrollment.progress_percent}%`,
                                }}
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {continueSlug ? (
                            <Button asChild>
                                <Link
                                    href={lessonRoute([
                                        course.slug,
                                        continueSlug,
                                    ])}
                                >
                                    <PlayCircle />
                                    {counts.completed > 0
                                        ? 'Lanjutkan belajar'
                                        : 'Mulai belajar'}
                                </Link>
                            </Button>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {counts.total === 0
                                    ? 'Materi kursus sedang disiapkan.'
                                    : counts.completed === counts.total
                                      ? 'Semua lesson sudah selesai. Hebat!'
                                      : 'Semua lesson yang terbuka sudah Anda selesaikan.'}
                            </p>
                        )}
                        {nextLocked?.unlock_at && (
                            <p className="text-sm text-muted-foreground">
                                Lesson berikutnya terbuka{' '}
                                {formatDate(nextLocked.unlock_at)}
                                {nextLocked.day_number
                                    ? ` (Hari ke-${nextLocked.day_number})`
                                    : ''}
                                .
                            </p>
                        )}
                    </div>
                </header>

                {modules.length === 0 ? (
                    <EmptyState
                        icon={PlayCircle}
                        title="Belum ada lesson"
                        description="Materi kursus sedang disiapkan oleh instruktur."
                    />
                ) : (
                    <LessonOutline courseSlug={course.slug} modules={modules} />
                )}

                {finalProject && (
                    <section
                        aria-label="Proyek akhir"
                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card p-4"
                    >
                        <div className="flex items-start gap-3">
                            <Trophy className="mt-0.5 size-5 text-primary" />
                            <div>
                                <p className="text-xs font-medium tracking-wide text-primary uppercase">
                                    Proyek akhir
                                </p>
                                <p className="font-semibold">
                                    {finalProject.title}
                                </p>
                                {finalProject.opens_at && (
                                    <p className="text-sm text-muted-foreground">
                                        Terbuka{' '}
                                        {formatDate(finalProject.opens_at)}
                                    </p>
                                )}
                            </div>
                        </div>
                        {!finalProject.opens_at && (
                            <Button asChild variant="outline">
                                <Link href={finalProjectRoute(course.slug)}>
                                    Buka proyek akhir
                                </Link>
                            </Button>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

LearnCourse.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus saya', href: myCourses() },
        { title: props.course.title, href: courseRoute(props.course.slug) },
    ],
});
