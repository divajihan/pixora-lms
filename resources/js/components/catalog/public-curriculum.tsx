import { Lock, PlayCircle, Radio, Video } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDuration } from '@/lib/format';
import type {
    PublicCurriculum as PublicCurriculumData,
    PublicLesson,
} from '@/types/catalog';

/**
 * Public curriculum. Only preview lessons carry an embed URL (server-side
 * rule); every other lesson is shown locked.
 */
export function PublicCurriculum({
    curriculum,
}: {
    curriculum: PublicCurriculumData;
}) {
    const [playing, setPlaying] = useState<PublicLesson | null>(null);

    if (curriculum.modules.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-6 text-muted-foreground">
                Daftar modul dan lesson segera hadir.
            </p>
        );
    }

    return (
        <>
            <p className="mb-4 text-sm text-muted-foreground">
                {curriculum.modules.length} modul · {curriculum.lesson_count}{' '}
                lesson · total {formatDuration(curriculum.duration_seconds)}
            </p>
            <div className="flex flex-col gap-3">
                {curriculum.modules.map((module) => (
                    <section
                        key={module.id}
                        className="overflow-hidden rounded-lg border"
                        aria-label={module.title}
                    >
                        <h3 className="border-b bg-muted/40 px-4 py-3 font-medium">
                            {module.title}
                        </h3>
                        <ol className="divide-y">
                            {module.lessons.map((lesson) => {
                                const TypeIcon =
                                    lesson.type === 'live' ? Radio : Video;
                                const canPlay = lesson.preview !== undefined;

                                return (
                                    <li
                                        key={lesson.id}
                                        className="flex items-center gap-3 px-4 py-3"
                                    >
                                        <TypeIcon
                                            className="size-4 shrink-0 text-muted-foreground"
                                            aria-label={
                                                lesson.type === 'live'
                                                    ? 'Kelas live'
                                                    : 'Video'
                                            }
                                        />
                                        <span className="min-w-0 flex-1 text-sm">
                                            {lesson.title}
                                        </span>
                                        {lesson.is_preview && (
                                            <Badge variant="secondary">
                                                Pratinjau
                                            </Badge>
                                        )}
                                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                            {formatDuration(
                                                lesson.duration_seconds,
                                            )}
                                        </span>
                                        {canPlay ? (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="size-8"
                                                aria-label={`Putar pratinjau ${lesson.title}`}
                                                onClick={() =>
                                                    setPlaying(lesson)
                                                }
                                            >
                                                <PlayCircle className="text-primary" />
                                            </Button>
                                        ) : (
                                            <Lock
                                                className="size-4 shrink-0 text-muted-foreground/60"
                                                aria-label="Terkunci"
                                            />
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                    </section>
                ))}
            </div>

            <Dialog
                open={playing !== null}
                onOpenChange={(open) => !open && setPlaying(null)}
            >
                <DialogContent className="gap-3 p-3 sm:max-w-3xl sm:p-4">
                    <DialogHeader>
                        <DialogTitle className="pr-8">
                            {playing?.title}
                        </DialogTitle>
                        <DialogDescription>Pratinjau gratis</DialogDescription>
                    </DialogHeader>
                    {playing?.preview && (
                        <div className="aspect-video w-full overflow-hidden rounded-md bg-black">
                            <iframe
                                src={playing.preview.embed_url}
                                title={`Pratinjau: ${playing.title}`}
                                className="size-full"
                                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                                referrerPolicy="strict-origin-when-cross-origin"
                                allowFullScreen
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
