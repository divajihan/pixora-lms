import { GraduationCap } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Course thumbnail with a neutral, token-based placeholder when none is uploaded.
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
    return (
        <div
            className={cn(
                'relative aspect-video w-full overflow-hidden bg-muted',
                className,
            )}
        >
            {src ? (
                <img
                    src={src}
                    alt={title}
                    loading="lazy"
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
