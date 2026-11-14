import { AssignmentPanel } from '@/components/assignments/assignment-panel';
import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Lock,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import { LessonOutline, unlockText } from '@/components/learn/lesson-outline';
import { YouTubePlayer } from '@/components/learn/youtube-player';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatDuration } from '@/lib/format';
import {
    complete,
    course as courseRoute,
    lesson as lessonRoute,
    uncomplete,
} from '@/routes/learn';
import { dashboard } from '@/routes/student';
import type { AssignmentPanelData } from '@/types/assignments';
import type {
    LearnCourse,
    LearnEnrollment,
    LessonContent,
    OutlineLesson,
    OutlineModule,
} from '@/types/learning';

type Neighbour = { slug: string; title: string; is_unlocked: boolean } | null;

type Props = {
    course: LearnCourse;
    enrollment: LearnEnrollment;
    modules: OutlineModule[];
    lesson: OutlineLesson;
    /** Null when the lesson is locked: the server sends no video/summary/meeting link. */
    content: LessonContent | null;
    previous: Neighbour;
    next: Neighbour;
    /** Only when the lesson is open and has a published assignment. */
    assignment: AssignmentPanelData | null;
};

export default function LearnLesson({
    course,
    enrollment,
    modules,
    lesson,
    content,
    previous,
    next,
    assignment,
}: Props) {
    const [saving, setSaving] = useState(false);
    const isCompleted = lesson.status === 'completed';

    const toggle = (markCompleted: boolean) => {
        const route = (markCompleted ? complete : uncomplete)([
            course.slug,
            lesson.slug,
        ]);

        router.visit(route.url, {
            method: route.method,
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <>
            <Head title={lesson.title} />
            <div className="grid flex-1 gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                <article className="flex min-w-0 flex-col gap-5">
                    <header className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            {course.title}
                            {lesson.day_number
                                ? ` · Hari ke-${lesson.day_number}`
                                : ''}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {lesson.title}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge variant="secondary">
                                {lesson.type === 'live'
                                    ? 'Kelas live'
                                    : 'Video'}
                            </Badge>
                            {lesson.duration_seconds ? (
                                <span>
                                    {formatDuration(lesson.duration_seconds)}
                                </span>
                            ) : null}
                            {isCompleted && (
                                <span className="inline-flex items-center gap-1 text-primary">
                                    <CheckCircle2 className="size-4" />
                                    Selesai
                                </span>
                            )}
                        </div>
                    </header>

                    {content === null ? (
                        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center">
                            <span className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Lock className="size-6" />
                            </span>
                            <p className="font-medium">
                                Lesson ini belum terbuka
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {unlockText(lesson)}.
                            </p>
                        </div>
                    ) : (
                        <>
                            {content.embed_url && (
                                <YouTubePlayer
                                    embedUrl={content.embed_url}
                                    title={lesson.title}
                                    onEnded={() => !isCompleted && toggle(true)}
                                />
                            )}

                            {content.live && (
                                <section
                                    aria-label="Jadwal kelas live"
                                    className="flex flex-col gap-2 rounded-lg border bg-card p-4"
                                >
                                    <p className="flex items-center gap-2 font-medium">
                                        <CalendarClock className="size-4" />
                                        {formatDateTime(content.live.starts_at)}
                                        {content.live.ends_at &&
                                            ` – ${formatDateTime(content.live.ends_at)}`}{' '}
                                        WIB
                                    </p>
                                    {content.live.platform && (
                                        <p className="text-sm text-muted-foreground">
                                            Platform: {content.live.platform}
                                        </p>
                                    )}
                                    {content.live.notes && (
                                        <p className="text-sm">
                                            {content.live.notes}
                                        </p>
                                    )}
                                    {content.live.meeting_url ? (
                                        <Button asChild className="self-start">
                                            <a
                                                href={content.live.meeting_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                Gabung kelas live
                                                <ExternalLink />
                                            </a>
                                        </Button>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Tautan meeting akan dibagikan
                                            instruktur.
                                        </p>
                                    )}
                                </section>
                            )}

                            <section
                                aria-label="Rangkuman"
                                className="rounded-lg border p-4"
                            >
                                <h2 className="mb-3 font-semibold">
                                    Rangkuman
                                </h2>
                                {content.summary_html ? (
                                    // Sanitised server-side (HtmlSanitizer) before it was saved.
                                    <div
                                        className="rich-text"
                                        dangerouslySetInnerHTML={{
                                            __html: content.summary_html,
                                        }}
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada rangkuman untuk lesson ini.
                                    </p>
                                )}
                            </section>

                            <div className="flex flex-wrap gap-2">
                                {isCompleted ? (
                                    <Button
                                        variant="outline"
                                        onClick={() => toggle(false)}
                                        disabled={saving}
                                    >
                                        {saving ? <Spinner /> : <RotateCcw />}
                                        Batalkan selesai
                                    </Button>
                                ) : (
                                    <Button
                                        onClick={() => toggle(true)}
                                        disabled={saving}
                                    >
                                        {saving ? (
                                            <Spinner />
                                        ) : (
                                            <CheckCircle2 />
                                        )}
                                        Tandai selesai
                                    </Button>
                                )}
                            </div>
                        </>
                    )}

                    {assignment && (
                        <AssignmentPanel
                            panel={assignment}
                            courseSlug={course.slug}
                        />
                    )}

                    <nav
                        aria-label="Navigasi lesson"
                        className="flex items-center justify-between gap-2 border-t pt-4"
                    >
                        {previous ? (
                            previous.is_unlocked ? (
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="max-w-[48%] min-w-0"
                                >
                                    <Link
                                        href={lessonRoute([
                                            course.slug,
                                            previous.slug,
                                        ])}
                                    >
                                        <ChevronLeft />
                                        <span className="truncate">
                                            {previous.title}
                                        </span>
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )
                        ) : (
                            <span />
                        )}
                        {next &&
                            (next.is_unlocked ? (
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="max-w-[48%] min-w-0"
                                >
                                    <Link
                                        href={lessonRoute([
                                            course.slug,
                                            next.slug,
                                        ])}
                                    >
                                        <span className="truncate">
                                            {next.title}
                                        </span>
                                        <ChevronRight />
                                    </Link>
                                </Button>
                            ) : (
                                <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                                    <Lock className="size-4" />
                                    Berikutnya belum terbuka
                                </span>
                            ))}
                    </nav>
                </article>

                <aside className="flex flex-col gap-3 lg:sticky lg:top-4 lg:max-h-[calc(100svh-6rem)] lg:self-start lg:overflow-y-auto">
                    <div className="rounded-lg border p-3">
                        <div className="mb-2 flex items-center justify-between text-sm">
                            <Link
                                href={courseRoute(course.slug)}
                                className="font-medium hover:underline"
                            >
                                Ikhtisar kursus
                            </Link>
                            <span className="text-muted-foreground">
                                {enrollment.progress_percent}%
                            </span>
                        </div>
                        <div
                            className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            aria-hidden
                        >
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{
                                    width: `${enrollment.progress_percent}%`,
                                }}
                            />
                        </div>
                    </div>
                    <div className="rounded-lg border p-2">
                        <LessonOutline
                            courseSlug={course.slug}
                            modules={modules}
                            currentSlug={lesson.slug}
                            compact
                        />
                    </div>
                </aside>
            </div>
        </>
    );
}

LearnLesson.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: props.course.title, href: courseRoute(props.course.slug) },
        {
            title: props.lesson.title,
            href: lessonRoute([props.course.slug, props.lesson.slug]),
        },
    ],
});
