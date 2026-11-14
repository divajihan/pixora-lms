import { router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { reset as resetDeadline, set as setDeadline } from '@/routes/classes/assignments/deadline';

/** ISO → "YYYY-MM-DDTHH:mm" in WIB for <input type="datetime-local">. */
function toLocalInput(iso: string): string {
    return new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
        .format(new Date(iso))
        .replace(' ', 'T');
}

/**
 * Extend a deadline for a whole batch or one student. The new deadline must
 * be in the future (checked on the server); "reset" removes the extension.
 */
export function DeadlineDialog({
    open,
    onOpenChange,
    courseId,
    assignmentId,
    scope,
    title,
    current,
    hasOverride,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    courseId: number;
    assignmentId: number;
    scope: { batch_id: number } | { enrollment_id: number };
    title: string;
    current: string;
    hasOverride: boolean;
}) {
    const [value, setValue] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setValue(toLocalInput(current));
            setError(undefined);
        }
    }, [open, current]);

    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => onOpenChange(false),
        onError: (errors: Record<string, string>) =>
            setError(
                errors.due_at ?? errors.batch_id ?? Object.values(errors)[0],
            ),
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(
            setDeadline([courseId, assignmentId]).url,
            { ...scope, due_at: value },
            options,
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Perpanjang tenggat</DialogTitle>
                        <DialogDescription>
                            {title}. Tenggat saat ini {formatDateTime(current)}{' '}
                            WIB. Status terlambat dihitung ulang otomatis.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="deadline-at">Tenggat baru (WIB)</Label>
                        <Input
                            id="deadline-at"
                            type="datetime-local"
                            value={value}
                            onChange={(event) => setValue(event.target.value)}
                        />
                        <InputError message={error} />
                    </div>
                    <DialogFooter className="sm:justify-between">
                        {hasOverride ? (
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={processing}
                                onClick={() =>
                                    router.delete(
                                        resetDeadline([courseId, assignmentId])
                                            .url,
                                        { ...options, data: scope },
                                    )
                                }
                            >
                                <RotateCcw />
                                Kembalikan ke jadwal semula
                            </Button>
                        ) : (
                            <span />
                        )}
                        <Button type="submit" disabled={processing || !value}>
                            {processing && <Spinner />}
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
