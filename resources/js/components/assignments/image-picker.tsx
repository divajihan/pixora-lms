import { ImagePlus, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * Chooses image files with local previews. The server re-checks everything
 * (content type, size, count) — this only gives early feedback.
 */
export function ImagePicker({
    files,
    onChange,
    max,
    accept,
    maxKb,
    maxLabel,
    disabled = false,
    label = 'Pilih gambar',
}: {
    files: File[];
    onChange: (files: File[]) => void;
    max: number;
    accept: string;
    maxKb: number;
    maxLabel: string;
    disabled?: boolean;
    label?: string;
}) {
    const inputId = useId();
    const input = useRef<HTMLInputElement>(null);
    const previews = useMemo(
        () => files.map((file) => URL.createObjectURL(file)),
        [files],
    );
    const allowed = accept.split(',');

    useEffect(
        () => () => previews.forEach((url) => URL.revokeObjectURL(url)),
        [previews],
    );

    const add = (list: FileList | null) => {
        if (!list) {
            return;
        }

        onChange([...files, ...Array.from(list)].slice(0, Math.max(0, max)));

        if (input.current) {
            input.current.value = '';
        }
    };

    return (
        <div className="flex flex-col gap-2">
            {files.length > 0 && (
                <ul className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                    {files.map((file, index) => {
                        const tooBig = file.size > maxKb * 1024;
                        const wrongType = !allowed.includes(file.type);

                        return (
                            <li
                                key={`${file.name}-${index}`}
                                className="relative"
                            >
                                <img
                                    src={previews[index]}
                                    alt={file.name}
                                    className={cn(
                                        'aspect-square w-full rounded-md border object-cover',
                                        (tooBig || wrongType) &&
                                            'border-destructive opacity-60',
                                    )}
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="secondary"
                                    className="absolute top-1 right-1 size-6"
                                    aria-label={`Hapus ${file.name}`}
                                    onClick={() =>
                                        onChange(
                                            files.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                                {(tooBig || wrongType) && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {wrongType
                                            ? 'Format tidak didukung'
                                            : `Lebih dari ${maxLabel}`}
                                    </p>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            {files.length < max && (
                <label
                    htmlFor={inputId}
                    className={cn(
                        'flex cursor-pointer flex-col items-center gap-1 rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground hover:bg-accent/50',
                        disabled && 'pointer-events-none opacity-50',
                    )}
                >
                    <ImagePlus className="size-5" />
                    <span className="font-medium text-foreground">{label}</span>
                    <span className="text-xs">
                        JPEG, PNG, atau WebP · maks {maxLabel} per gambar ·{' '}
                        {max - files.length} lagi
                    </span>
                    <input
                        ref={input}
                        id={inputId}
                        type="file"
                        accept={accept}
                        multiple
                        disabled={disabled}
                        className="sr-only"
                        onChange={(event) => add(event.target.files)}
                    />
                </label>
            )}
        </div>
    );
}
