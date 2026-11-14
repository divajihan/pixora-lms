import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export type PlaceholderItem = {
    title: string;
    description: string;
    icon: LucideIcon;
    /** Phase in which the feature arrives (shown as "Segera hadir"). */
    phase?: number;
    /** Available now: the card links here. */
    href?: NonNullable<InertiaLinkProps['href']>;
};

export function DashboardPlaceholder({
    greeting,
    description,
    items,
    action,
}: {
    greeting: string;
    description: string;
    items: PlaceholderItem[];
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {greeting}
                    </h1>
                    <p className="text-muted-foreground">{description}</p>
                </div>
                {action}
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((item) => {
                    const content = (
                        <Card className="h-full gap-3 transition-shadow group-hover:shadow-md">
                            <CardHeader>
                                <div className="flex items-start justify-between gap-2">
                                    <span className="flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                        <item.icon className="size-4" />
                                    </span>
                                    {item.href ? (
                                        <ArrowRight className="size-4 text-muted-foreground group-hover:text-foreground" />
                                    ) : (
                                        <Badge variant="secondary">
                                            Segera hadir
                                            {item.phase
                                                ? ` · Fase ${item.phase}`
                                                : ''}
                                        </Badge>
                                    )}
                                </div>
                                <CardTitle className="pt-2">
                                    {item.title}
                                </CardTitle>
                                <CardDescription>
                                    {item.description}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    );

                    return item.href ? (
                        <Link
                            key={item.title}
                            href={item.href}
                            className="group rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {content}
                        </Link>
                    ) : (
                        <div key={item.title}>{content}</div>
                    );
                })}
            </div>
        </div>
    );
}
