import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PaginationLink } from '@/types/catalog';

/**
 * Renders Laravel paginator links. The first/last entries are prev/next;
 * their (HTML-entity) labels from the server are replaced with our own.
 */
export function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    const prev = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1);

    const base =
        'inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-md border px-3 text-sm transition-colors';

    const item = (
        link: PaginationLink,
        content: React.ReactNode,
        key: string,
        label?: string,
    ) =>
        link.url ? (
            <Link
                key={key}
                href={link.url}
                preserveScroll={false}
                aria-label={label}
                aria-current={link.active ? 'page' : undefined}
                className={cn(
                    base,
                    link.active
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'bg-background hover:bg-accent',
                )}
            >
                {content}
            </Link>
        ) : (
            <span
                key={key}
                aria-disabled
                className={cn(
                    base,
                    'cursor-default text-muted-foreground opacity-60',
                )}
            >
                {content}
            </span>
        );

    return (
        <nav
            aria-label="Navigasi halaman"
            className="flex flex-wrap items-center justify-center gap-1.5"
        >
            {item(
                prev,
                <>
                    <ChevronLeft className="size-4" />
                    <span className="hidden sm:inline">Sebelumnya</span>
                </>,
                'prev',
                'Halaman sebelumnya',
            )}
            {pages.map((link, index) =>
                item(
                    link,
                    link.label,
                    `page-${index}`,
                    `Halaman ${link.label}`,
                ),
            )}
            {item(
                next,
                <>
                    <span className="hidden sm:inline">Berikutnya</span>
                    <ChevronRight className="size-4" />
                </>,
                'next',
                'Halaman berikutnya',
            )}
        </nav>
    );
}
