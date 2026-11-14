import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Lock,
    Send,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { CommentThread } from '@/components/assignments/comment-thread';
import { ImageGallery } from '@/components/assignments/image-gallery';
import { ImagePicker } from '@/components/assignments/image-picker';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, formatRemaining } from '@/lib/format';
import {
    submit as submitRoute,
    update as updateRoute,
} from '@/routes/learn/assignments';
import type { AssignmentPanelData, StudentState } from '@/types/assignments';

const stateVariant: Record<
    StudentState,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    not_submitted: 'outline',
    on_time: 'secondary',
    late: 'destructive',
    feedback: 'default',
};

/**
 * Student assignment block: instructions, effective deadline (WIB + time
 * left), upload with previews, own submission and the feedback thread
 * (read-only: students cannot reply). The server enforces every rule.
 */
export function AssignmentPanel({
    panel,
    courseSlug,
}: {
    panel: AssignmentPanelData;
    courseSlug: string;
}) {
    const { assignment, deadline, submission, limits } = panel;
    const [remaining, setRemaining] = useState<string | null>(null);
    const [editing, setEditing] = useState(false);
    const form = useForm<{ images: File[]; body: string; remove: number[] }>({
        images: [],
        body: submission?.body ?? '',
        remove: [],
    });

    useEffect(() => {
        const tick = () => setRemaining(formatRemaining(deadline.due_at));
        tick();
        const timer = window.setInterval(tick, 60_000);

        return () => window.clearInterval(timer);
    }, [deadline.due_at]);

    useEffect(() => {
        form.setData({ images: [], body: submission?.body ?? '', remove: [] });
        setEditing(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset when the server state changes
    }, [submission?.id, submission?.images.length, submission?.body]);

    const route = submission
        ? updateRoute([courseSlug, assignment.id])
        : submitRoute([courseSlug, assignment.id]);
    const keptImages =
        (submission?.images.length ?? 0) - form.data.remove.length;
    const slots = limits.max_images - keptImages;

    const send = (event: FormEvent) => {
        event.preventDefault();
        form.post(route.url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const errorMessage =
        form.errors.images ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('images.'),
        )?.[1];

    return (
        <section
            aria-labelledby={`assignment-${assignment.id}`}
            className="flex flex-col gap-4 rounded-lg border bg-card p-4"
        >
            <header className="flex flex-wrap items-start justify-between gap-2">
                <div className="space-y-1">
                    <p className="flex items-center gap-1 text-xs font-medium tracking-wide text-primary uppercase">
                        <ClipboardList className="size-4" />
                        {assignment.is_final_project ? 'Proyek akhir' : 'Tugas'}
                    </p>
                    <h2
                        id={`assignment-${assignment.id}`}
                        className="text-lg font-semibold"
                    >
                        {assignment.title}
                    </h2>
                </div>
                <Badge variant={stateVariant[panel.state]}>
                    {panel.state_label}
                </Badge>
            </header>

            <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <CalendarClock className="size-4 text-muted-foreground" />
                <span>
                    Tenggat{' '}
                    <strong>{formatDateTime(deadline.due_at)} WIB</strong>
                </span>
                {remaining && (
                    <span
                        className={
                            deadline.has_passed
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        }
                    >
                        ({remaining})
                    </span>
                )}
                {deadline.is_extended && (
                    <Badge variant="secondary">Diperpanjang</Badge>
                )}
            </p>

            {assignment.instructions_html && (
                // Sanitised server-side (HtmlSanitizer) before it was saved.
                <div
                    className="rich-text text-sm"
                    dangerouslySetInnerHTML={{
                        __html: assignment.instructions_html,
                    }}
                />
            )}

            {submission && (
                <div className="flex flex-col gap-3 rounded-md border p-3">
                    <p className="flex flex-wrap items-center gap-2 text-sm">
                        {submission.is_late ? (
                            <AlertTriangle className="size-4 text-destructive" />
                        ) : (
                            <CheckCircle2 className="size-4 text-primary" />
                        )}
                        Dikumpulkan {formatDateTime(submission.submitted_at)}{' '}
                        WIB
                        {submission.is_late && (
                            <span className="text-destructive">
                                — terlambat, tidak mendapat feedback
                            </span>
                        )}
                    </p>
                    {!editing && (
                        <>
                            <ImageGallery images={submission.images} />
                            {submission.body && (
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    {submission.body}
                                </p>
                            )}
                            {submission.can_edit ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="self-start"
                                    onClick={() => setEditing(true)}
                                >
                                    Ubah pengumpulan
                                </Button>
                            ) : (
                                submission.edit_blocker && (
                                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Lock className="size-3" />
                                        {submission.edit_blocker}
                                    </p>
                                )
                            )}
                        </>
                    )}
                </div>
            )}

            {(!submission || editing) && (
                <form
                    onSubmit={send}
                    className="flex flex-col gap-3"
                    noValidate
                >
                    {deadline.has_passed && !submission && (
                        <Alert variant="destructive">
                            <AlertTriangle />
                            <AlertDescription>
                                Tenggat sudah lewat. Anda masih bisa
                                mengumpulkan, tetapi akan tercatat{' '}
                                <strong>Terlambat</strong>, tidak bisa diubah
                                lagi, dan tidak mendapat feedback.
                            </AlertDescription>
                        </Alert>
                    )}
                    {submission && (
                        <div className="flex flex-col gap-1">
                            <Label>Gambar saat ini</Label>
                            <ImageGallery
                                images={submission.images}
                                removable={{
                                    removed: form.data.remove,
                                    onToggle: (id) =>
                                        form.setData(
                                            'remove',
                                            form.data.remove.includes(id)
                                                ? form.data.remove.filter(
                                                      (value) => value !== id,
                                                  )
                                                : [...form.data.remove, id],
                                        ),
                                }}
                            />
                        </div>
                    )}
                    <ImagePicker
                        files={form.data.images}
                        onChange={(files) => form.setData('images', files)}
                        max={Math.max(0, slots)}
                        accept={limits.accept}
                        maxKb={limits.max_image_kb}
                        maxLabel={limits.max_image_label}
                        disabled={form.processing}
                        label={
                            submission ? 'Tambah gambar' : 'Pilih gambar tugas'
                        }
                    />
                    <InputError message={errorMessage} />
                    <div className="grid gap-1">
                        <Label htmlFor={`body-${assignment.id}`}>
                            Catatan (opsional)
                        </Label>
                        <Textarea
                            id={`body-${assignment.id}`}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            maxLength={1000}
                            rows={2}
                            placeholder="Contoh: bagian yang ingin saya tanyakan…"
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                (!submission &&
                                    form.data.images.length === 0) ||
                                keptImages + form.data.images.length < 1
                            }
                        >
                            {form.processing ? <Spinner /> : <Send />}
                            {submission
                                ? 'Simpan perubahan'
                                : 'Kumpulkan tugas'}
                        </Button>
                        {editing && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setEditing(false)}
                            >
                                Batal
                            </Button>
                        )}
                        {form.progress && (
                            <span className="text-xs text-muted-foreground">
                                Mengunggah {form.progress.percentage}%
                            </span>
                        )}
                    </div>
                </form>
            )}

            {submission && (
                <div className="flex flex-col gap-2">
                    <h3 className="text-sm font-semibold">Feedback</h3>
                    <CommentThread
                        comments={submission.comments}
                        emptyText={
                            submission.is_late
                                ? 'Pengumpulan terlambat tidak mendapat feedback.'
                                : 'Instruktur akan memberi feedback setelah tenggat berakhir.'
                        }
                    />
                </div>
            )}
        </section>
    );
}
