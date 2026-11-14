import { router } from '@inertiajs/react';
import { MessageSquare, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { ImageGallery } from '@/components/assignments/image-gallery';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { destroy, update } from '@/routes/submissions/comments';
import type { CommentData } from '@/types/assignments';

function CommentItem({
    comment,
    manage,
}: {
    comment: CommentData;
    manage: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const [body, setBody] = useState(comment.body ?? '');
    const [error, setError] = useState<string | undefined>();
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const canModify = manage && comment.is_mine;

    const save = () =>
        router.put(
            update(comment.id).url,
            { body },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => {
                    setEditing(false);
                    setError(undefined);
                },
                onError: (errors) => setError(errors.body),
            },
        );

    return (
        <li className="flex flex-col gap-2 rounded-lg border bg-card p-3">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <span className="font-medium">{comment.author}</span>
                <Badge
                    variant={
                        comment.role === 'instructor' ? 'default' : 'secondary'
                    }
                >
                    {comment.role_label}
                </Badge>
                <span className="text-xs text-muted-foreground">
                    {formatDateTime(comment.created_at)} WIB
                    {comment.edited_at && ' · diedit'}
                </span>
                {canModify && !editing && (
                    <span className="ml-auto flex gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label="Ubah komentar"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label="Hapus komentar"
                            onClick={() => setDeleting(true)}
                        >
                            <Trash2 />
                        </Button>
                    </span>
                )}
            </div>
            {editing ? (
                <div className="flex flex-col gap-2">
                    <Textarea
                        value={body}
                        onChange={(event) => setBody(event.target.value)}
                        rows={3}
                        aria-label="Ubah komentar"
                    />
                    <InputError message={error} />
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            onClick={save}
                            disabled={saving}
                        >
                            {saving && <Spinner />}
                            Simpan
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setEditing(false)}
                        >
                            Batal
                        </Button>
                    </div>
                </div>
            ) : (
                comment.body && (
                    <p className="text-sm whitespace-pre-line">
                        {comment.body}
                    </p>
                )
            )}
            <ImageGallery
                images={comment.images}
                showDownload={manage}
                size="sm"
            />

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title="Hapus komentar?"
                description="Komentar dan gambarnya tidak lagi terlihat oleh siswa."
                onConfirm={() =>
                    router.delete(destroy(comment.id).url, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(false),
                    })
                }
            />
        </li>
    );
}

/**
 * Feedback thread. Students read it (no reply form); reviewers may edit or
 * delete their own comments (`manage`).
 */
export function CommentThread({
    comments,
    manage = false,
    emptyText,
}: {
    comments: CommentData[];
    manage?: boolean;
    emptyText?: string;
}) {
    if (comments.length === 0) {
        return emptyText ? (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <MessageSquare className="size-4" />
                {emptyText}
            </p>
        ) : null;
    }

    return (
        <ol className="flex flex-col gap-3">
            {comments.map((comment) => (
                <CommentItem
                    key={comment.id}
                    comment={comment}
                    manage={manage}
                />
            ))}
        </ol>
    );
}
