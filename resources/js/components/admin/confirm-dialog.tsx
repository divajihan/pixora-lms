import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * Confirmation dialog for destructive actions. `onConfirm` should perform the
 * request; the dialog shows a spinner while `processing` is true.
 */
export function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel = 'Hapus',
    processing = false,
    open,
    onOpenChange,
    onConfirm,
}: {
    trigger?: ReactNode;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    processing?: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    onConfirm: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary" disabled={processing}>
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={onConfirm}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
