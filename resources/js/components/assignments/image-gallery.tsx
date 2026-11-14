import {
    ChevronLeft,
    ChevronRight,
    Download,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import type { AttachmentData } from '@/types/assignments';

/**
 * Thumbnails with a lightbox. Files come from policy-checked URLs only.
 * Optional "mark for removal" mode for the student edit form.
 */
export function ImageGallery({
    images,
    showDownload = false,
    removable,
    size = 'md',
}: {
    images: AttachmentData[];
    showDownload?: boolean;
    removable?: { removed: number[]; onToggle: (id: number) => void };
    size?: 'sm' | 'md';
}) {
    const [open, setOpen] = useState<number | null>(null);
    const current = open !== null ? images[open] : null;

    if (images.length === 0) {
        return null;
    }

    return (
        <>
            <ul
                className={cn(
                    'grid gap-2',
                    size === 'sm'
                        ? 'grid-cols-4 sm:grid-cols-6'
                        : 'grid-cols-2 sm:grid-cols-4',
                )}
            >
                {images.map((image, index) => {
                    const removed =
                        removable?.removed.includes(image.id) ?? false;

                    return (
                        <li key={image.id} className="relative">
                            <button
                                type="button"
                                onClick={() => setOpen(index)}
                                className="block w-full overflow-hidden rounded-md border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                aria-label={`Lihat ${image.name}`}
                            >
                                <img
                                    src={image.thumbnail_url}
                                    alt={image.name}
                                    loading="lazy"
                                    className={cn(
                                        'aspect-square w-full bg-muted object-cover',
                                        removed && 'opacity-30 grayscale',
                                    )}
                                />
                            </button>
                            {removable && (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="secondary"
                                    className="absolute top-1 right-1 size-7"
                                    aria-label={
                                        removed
                                            ? `Batal hapus ${image.name}`
                                            : `Hapus ${image.name}`
                                    }
                                    onClick={() => removable.onToggle(image.id)}
                                >
                                    {removed ? <Undo2 /> : <Trash2 />}
                                </Button>
                            )}
                            {showDownload && (
                                <a
                                    href={image.download_url}
                                    className="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                >
                                    <Download className="size-3" />
                                    Unduh asli
                                </a>
                            )}
                        </li>
                    );
                })}
            </ul>

            <Dialog
                open={current !== null}
                onOpenChange={(value) => !value && setOpen(null)}
            >
                <DialogContent className="max-w-[min(96vw,1100px)] gap-3 p-3 sm:max-w-[min(96vw,1100px)]">
                    {current && open !== null && (
                        <>
                            <DialogTitle className="truncate pr-8 text-sm">
                                {current.name}
                            </DialogTitle>
                            <DialogDescription className="sr-only">
                                Gambar {open + 1} dari {images.length}
                            </DialogDescription>
                            <div className="flex max-h-[75vh] items-center justify-center overflow-auto rounded-md bg-muted">
                                <img
                                    src={current.url}
                                    alt={current.name}
                                    className="max-h-[75vh] w-auto max-w-full object-contain"
                                />
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={open === 0}
                                    onClick={() => setOpen(open - 1)}
                                >
                                    <ChevronLeft />
                                    Sebelumnya
                                </Button>
                                <span className="text-xs text-muted-foreground">
                                    {open + 1} / {images.length} ·{' '}
                                    {current.width}×{current.height}
                                </span>
                                <div className="flex gap-1">
                                    <Button asChild variant="outline" size="sm">
                                        <a href={current.download_url}>
                                            <Download />
                                            Unduh asli
                                        </a>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={open === images.length - 1}
                                        onClick={() => setOpen(open + 1)}
                                    >
                                        Berikutnya
                                        <ChevronRight />
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
