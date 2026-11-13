import {
    CalendarClock,
    ChevronDown,
    ExternalLink,
    Eye,
    Radio,
    Video,
} from 'lucide-react';
import { StatusBadge } from '@/components/admin/status-badge';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatDateTime, formatDuration } from '@/lib/format';
import type { CurriculumModule } from '@/types/admin';

/**
 * Read-only curriculum for staff (instructor view). Lesson summaries are
 * HTML sanitised on the server before they were saved.
 */
export function CurriculumReadonly({
    modules,
}: {
    modules: CurriculumModule[];
}) {
    return (
        <div className="flex flex-col gap-4">
            {modules.map((module) => (
                <section
                    key={module.id}
                    className="rounded-lg border"
                    aria-label={module.title}
                >
                    <header className="border-b bg-muted/40 px-4 py-3">
                        <h2 className="font-semibold">{module.title}</h2>
                        <p className="text-xs text-muted-foreground">
                            {module.lessons.length} lesson ·{' '}
                            {formatDuration(
                                module.lessons.reduce(
                                    (sum, lesson) =>
                                        sum + (lesson.duration_seconds ?? 0),
                                    0,
                                ),
                            )}
                        </p>
                    </header>
                    {module.lessons.length === 0 ? (
                        <p className="px-4 py-3 text-sm text-muted-foreground">
                            Belum ada lesson.
                        </p>
                    ) : (
                        <ol className="divide-y">
                            {module.lessons.map((lesson) => {
                                const TypeIcon =
                                    lesson.type === 'live' ? Radio : Video;

                                return (
                                    <li key={lesson.id}>
                                        <Collapsible>
                                            <CollapsibleTrigger className="group flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-accent/50">
                                                <TypeIcon
                                                    className="size-4 shrink-0 text-muted-foreground"
                                                    aria-label={
                                                        lesson.type === 'live'
                                                            ? 'Kelas live'
                                                            : 'Video'
                                                    }
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-medium">
                                                        {lesson.title}
                                                    </span>
                                                    <span className="flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                                                        <span>
                                                            {formatDuration(
                                                                lesson.duration_seconds,
                                                            )}
                                                        </span>
                                                        <span>
                                                            Hari ke-
                                                            {
                                                                lesson.drip_day_offset
                                                            }
                                                        </span>
                                                    </span>
                                                </span>
                                                {lesson.is_preview && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="hidden sm:inline-flex"
                                                    >
                                                        <Eye />
                                                        Pratinjau
                                                    </Badge>
                                                )}
                                                {lesson.status === 'draft' && (
                                                    <StatusBadge status="draft" />
                                                )}
                                                <ChevronDown className="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                                            </CollapsibleTrigger>
                                            <CollapsibleContent className="space-y-3 px-4 pb-4 pl-11">
                                                {lesson.live && (
                                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md bg-muted/50 p-2 text-sm">
                                                        <span className="inline-flex items-center gap-1">
                                                            <CalendarClock className="size-4" />
                                                            {formatDateTime(
                                                                lesson.live
                                                                    .starts_at,
                                                            )}
                                                            {lesson.live
                                                                .ends_at &&
                                                                ` – ${formatDateTime(lesson.live.ends_at)}`}
                                                        </span>
                                                        {lesson.live
                                                            .platform && (
                                                            <span>
                                                                {
                                                                    lesson.live
                                                                        .platform
                                                                }
                                                            </span>
                                                        )}
                                                        {lesson.live
                                                            .meeting_url && (
                                                            <a
                                                                href={
                                                                    lesson.live
                                                                        .meeting_url
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="inline-flex items-center gap-1 text-primary underline"
                                                            >
                                                                Tautan meeting{' '}
                                                                <ExternalLink className="size-3" />
                                                            </a>
                                                        )}
                                                    </div>
                                                )}
                                                {lesson.video_url && (
                                                    <a
                                                        href={lesson.video_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 text-sm text-primary underline"
                                                    >
                                                        Buka video di YouTube{' '}
                                                        <ExternalLink className="size-3" />
                                                    </a>
                                                )}
                                                {lesson.summary_html ? (
                                                    // Sanitised server-side (HtmlSanitizer) before saving.
                                                    <div
                                                        className="rich-text"
                                                        dangerouslySetInnerHTML={{
                                                            __html: lesson.summary_html,
                                                        }}
                                                    />
                                                ) : (
                                                    <p className="text-sm text-muted-foreground">
                                                        Belum ada ringkasan.
                                                    </p>
                                                )}
                                            </CollapsibleContent>
                                        </Collapsible>
                                    </li>
                                );
                            })}
                        </ol>
                    )}
                </section>
            ))}
        </div>
    );
}
