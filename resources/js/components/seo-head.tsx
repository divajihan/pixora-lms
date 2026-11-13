import { Head } from '@inertiajs/react';

/**
 * Page title + meta description. The server renders the same tags from the
 * page's `meta` prop (resources/views/app.blade.php) for crawlers; on the
 * client this replaces them (shared `head-key="description"`).
 */
export function SeoHead({
    title,
    description,
}: {
    title?: string | null;
    description: string;
}) {
    return (
        <Head title={title ?? undefined}>
            <meta
                head-key="description"
                name="description"
                content={description}
            />
        </Head>
    );
}
