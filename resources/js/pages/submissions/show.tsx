import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ChevronLeft, Clock, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { CommentThread } from '@/components/assignments/comment-thread';
import { ImageGallery } from '@/components/assignments/image-gallery';
import { ImagePicker } from '@/components/assignments/image-picker';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { show } from '@/routes/submissions';
import { store } from '@/routes/submissions/comments';
import type {
    AttachmentData,
    CommentData,
    ReviewState,
} from '@/types/assignments';

type Props = {
    submission: {
        id: number;
        body: string | null;
        submitted_at: string;
        is_late: boolean;
        state: ReviewState;
        state_label: string;
        reviewed_at: string | null;
        reviewer: string | null;
        images: AttachmentData[];
        comments: CommentData[];
    };
    student: { name: string; email: string; batch: string | null };
    assignment: {
        id: number;
        title: string;
        instructions_html: string | null;
        is_final_project: boolean;
        lesson: string | null;
        due_at: string;
        is_extended: boolean;
    };
    course: { id: number; title: string } | null;
    backUrl: string;
    canComment: boolean;
    commentBlocker: string | null;
    imageLimits: {
        max_images: number;
        max_image_kb: number;
        max_image_label: string;
        accept: string;
    };
};

export default function SubmissionShow({
    submission,
    student,
    assignment,
    course,
    backUrl,
    canComment,
    commentBlocker,
    imageLimits,
}: Props) {
    const form = useForm<{ body: string; images: File[] }>({
        body: '',
        images: [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store(submission.id).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const imageError =
        form.errors.images ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('images.'),
        )?.[1];

    return (
        <>
            <Head title={`${student.name} — ${assignment.title}`} />
            <div className="mx-auto grid w-full max-w-6xl flex-1 gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="flex min-w-0 flex-col gap-5">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="self-start"
                    >
                        <Link href={backUrl}>
                            <ChevronLeft />
                            Kembali ke daftar
                        </Link>
                    </Button>

                    <header className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            {course?.title} ·{' '}
                            {assignment.is_final_project
                                ? 'Proyek akhir'
                                : assignment.lesson}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {assignment.title}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="font-medium">{student.name}</span>
                            <span className="text-muted-foreground">
                                {student.email}
                            </span>
                            {student.batch && (
                                <Badge variant="outline">{student.batch}</Badge>
                            )}
                            <Badge
                                variant={
                                    submission.state === 'late'
                                        ? 'destructive'
                                        : submission.state === 'needs_review'
                                          ? 'default'
                                          : 'secondary'
                                }
                            >
                                {submission.state_label}
                            </Badge>
                        </div>
                    </header>

                    {submission.is_late && (
                        <Alert variant="destructive">
                            <AlertTriangle />
                            <AlertTitle>
                                Terlambat - tidak mendapat feedback
                            </AlertTitle>
                            <AlertDescription>
                                Dikumpulkan setelah tenggat{' '}
                                {formatDateTime(assignment.due_at)} WIB.
                            </AlertDescription>
                        </Alert>
                    )}

                    <section
                        aria-label="Gambar siswa"
                        className="flex flex-col gap-2"
                    >
                        <h2 className="font-semibold">
                            Gambar siswa ({submission.images.length})
                        </h2>
                        <ImageGallery images={submission.images} showDownload />
                        {submission.body && (
                            <p className="rounded-md bg-muted/50 p-3 text-sm whitespace-pre-line">
                                <span className="block text-xs font-medium text-muted-foreground">
                                    Catatan siswa
                                </span>
                                {submission.body}
                            </p>
                        )}
                    </section>

                    <section
                        aria-label="Komentar"
                        className="flex flex-col gap-3"
                    >
                        <h2 className="font-semibold">Komentar</h2>
                        <CommentThread
                            comments={submission.comments}
                            manage
                            emptyText="Belum ada komentar."
                        />

                        {canComment ? (
                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-3 rounded-lg border p-3"
                                noValidate
                            >
                                <div className="grid gap-1">
                                    <Label htmlFor="comment-body">
                                        Komentar baru
                                    </Label>
                                    <Textarea
                                        id="comment-body"
                                        value={form.data.body}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        rows={3}
                                        placeholder="Tulis feedback, dan/atau unggah gambar hasil coretan…"
                                    />
                                    <InputError message={form.errors.body} />
                                </div>
                                <ImagePicker
                                    files={form.data.images}
                                    onChange={(files) =>
                                        form.setData('images', files)
                                    }
                                    max={imageLimits.max_images}
                                    accept={imageLimits.accept}
                                    maxKb={imageLimits.max_image_kb}
                                    maxLabel={imageLimits.max_image_label}
                                    disabled={form.processing}
                                    label="Lampirkan gambar coretan"
                                />
                                <InputError message={imageError} />
                                <Button
                                    type="submit"
                                    className="self-start"
                                    disabled={
                                        form.processing ||
                                        (form.data.body.trim() === '' &&
                                            form.data.images.length === 0)
                                    }
                                >
                                    {form.processing ? <Spinner /> : <Send />}
                                    Kirim komentar
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    Komentar terlihat oleh siswa. Siswa tidak
                                    dapat membalas.
                                </p>
                            </form>
                        ) : (
                            <p className="flex items-center gap-2 rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                                <Clock className="size-4" />
                                {commentBlocker}
                            </p>
                        )}
                    </section>
                </div>

                <aside className="flex flex-col gap-3 lg:sticky lg:top-4 lg:self-start">
                    <div className="rounded-lg border p-4 text-sm">
                        <dl className="grid gap-2">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Dikumpulkan
                                </dt>
                                <dd>
                                    {formatDateTime(submission.submitted_at)}{' '}
                                    WIB
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Tenggat
                                </dt>
                                <dd>
                                    {formatDateTime(assignment.due_at)} WIB{' '}
                                    {assignment.is_extended && (
                                        <Badge variant="secondary">
                                            Diperpanjang
                                        </Badge>
                                    )}
                                </dd>
                            </div>
                            {submission.reviewed_at && (
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Direview
                                    </dt>
                                    <dd>
                                        {formatDateTime(submission.reviewed_at)}{' '}
                                        WIB{' '}
                                        {submission.reviewer &&
                                            `oleh ${submission.reviewer}`}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>
                    {assignment.instructions_html && (
                        <div className="rounded-lg border p-4">
                            <p className="mb-2 text-xs font-medium text-muted-foreground">
                                Instruksi tugas
                            </p>
                            <div
                                className="rich-text text-sm"
                                dangerouslySetInnerHTML={{
                                    __html: assignment.instructions_html,
                                }}
                            />
                        </div>
                    )}
                </aside>
            </div>
        </>
    );
}

SubmissionShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: props.assignment.title, href: props.backUrl },
        { title: props.student.name, href: show(props.submission.id) },
    ],
});
