import { GraduationCap } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Course thumbnail with a neutral, token-based placeholder when none is
 * uploaded or the image fails to load (never a broken-image icon).
 */
export function CourseThumbnail({
    src,
    title,
    className,
}: {
    src: string | null;
    title: string;
    className?: string;
}) {
    const [failedSrc, setFailedSrc] = useState<string | null>(null);
    const showImage = src !== null && src !== failedSrc;

    return (
        <div
            className={cn(
                'relative aspect-video w-full overflow-hidden bg-muted',
                className,
            )}
        >
            {showImage ? (
                <img
                    src={src}
                    alt={title}
                    loading="lazy"
                    decoding="async"
                    onError={() => setFailedSrc(src)}
                    className="size-full object-cover"
                />
            ) : (
                <div
                    role="img"
                    aria-label={`Gambar sampul ${title} belum tersedia`}
                    className="flex size-full items-center justify-center bg-gradient-to-br from-primary/15 via-muted to-muted"
                >
                    <GraduationCap
                        aria-hidden
                        className="size-10 text-primary/60"
                    />
                </div>
            )}
        </div>
    );
}
