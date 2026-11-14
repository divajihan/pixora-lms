import { Link } from '@inertiajs/react';
import { CheckCircle2, Circle, Lock, Radio, Video } from 'lucide-react';
import { formatDuration, formatUnlock } from '@/lib/format';
import { cn } from '@/lib/utils';
import { lesson as lessonRoute } from '@/routes/learn';
import type { OutlineLesson, OutlineModule } from '@/types/learning';

export function LessonStatusIcon({
    status,
    className,
}: {
    status: OutlineLesson['status'];
    className?: string;
}) {
    if (status === 'completed') {
        return (
            <CheckCircle2
                className={cn('size-4 text-primary', className)}
                aria-label="Selesai"
            />
        );
    }

    if (status === 'locked') {
        return (
            <Lock
                className={cn('size-4 text-muted-foreground', className)}
                aria-label="Terkunci"
            />
        );
    }

    return (
        <Circle
            className={cn('size-4 text-muted-foreground', className)}
            aria-label="Terbuka"
        />
    );
}

/**
 * "Terbuka Senin, 16 November 2026 00.00 · Hari ke-2"
 */
export function unlockText(lesson: OutlineLesson): string {
    const date = lesson.unlock_at
        ? `Terbuka ${formatUnlock(lesson.unlock_at)}`
        : 'Segera terbuka';

    return lesson.day_number ? `${date} · Hari ke-${lesson.day_number}` : date;
}

/**
 * Course outline with per-lesson status. Locked lessons are listed (title and
 * unlock date only) but not linked.
 */
export function LessonOutline({
    courseSlug,
    modules,
    currentSlug,
    compact = false,
}: {
    courseSlug: string;
    modules: OutlineModule[];
    currentSlug?: string;
    compact?: boolean;
}) {
    return (
        <nav aria-label="Kurikulum" className="flex flex-col gap-4">
            {modules.map((module) => (
                <section key={module.id} aria-label={module.title}>
                    <h3
                        className={cn(
                            'mb-1 font-semibold',
                            compact
                                ? 'px-2 text-xs tracking-wide text-muted-foreground uppercase'
                                : 'text-base',
                        )}
                    >
                        {module.title}
                    </h3>
                    <ol
                        className={cn(!compact && 'divide-y rounded-lg border')}
                    >
                        {module.lessons.map((lesson) => {
                            const TypeIcon =
                                lesson.type === 'live' ? Radio : Video;
                            const isCurrent = lesson.slug === currentSlug;
                            const body = (
                                <>
                                    <LessonStatusIcon
                                        status={lesson.status}
                                        className="mt-0.5 shrink-0"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={cn(
                                                'block text-sm',
                                                lesson.status === 'locked' &&
                                                    'text-muted-foreground',
                                                isCurrent && 'font-semibold',
                                            )}
                                        >
                                            {lesson.title}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                            <TypeIcon
                                                className="size-3"
                                                aria-label={
                                                    lesson.type === 'live'
                                                        ? 'Kelas live'
                                                        : 'Video'
                                                }
                                            />
                                            {lesson.status === 'locked' ? (
                                                <span>
                                                    {unlockText(lesson)}
                                                </span>
                                            ) : (
                                                <>
                                                    <span>
                                                        {formatDuration(
                                                            lesson.duration_seconds,
                                                        )}
                                                    </span>
                                                    {lesson.day_number &&
                                                        !compact && (
                                                            <span>
                                                                Hari ke-
                                                                {
                                                                    lesson.day_number
                                                                }
                                                            </span>
                                                        )}
                                                </>
                                            )}
                                        </span>
                                    </span>
                                </>
                            );

                            return (
                                <li key={lesson.id}>
                                    {lesson.status === 'locked' ? (
                                        <div
                                            className={cn(
                                                'flex gap-3 px-3 py-2.5',
                                                compact && 'px-2 py-2',
                                            )}
                                            aria-disabled
                                        >
                                            {body}
                                        </div>
                                    ) : (
                                        <Link
                                            href={lessonRoute([
                                                courseSlug,
                                                lesson.slug,
                                            ])}
                                            aria-current={
                                                isCurrent ? 'page' : undefined
                                            }
                                            className={cn(
                                                'flex gap-3 px-3 py-2.5 hover:bg-accent/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                compact &&
                                                    'rounded-md px-2 py-2',
                                                isCurrent && 'bg-accent',
                                            )}
                                        >
                                            {body}
                                        </Link>
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                </section>
            ))}
        </nav>
    );
}
