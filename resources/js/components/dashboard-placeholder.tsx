import type { LucideIcon } from 'lucide-react';
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
    phase: number;
    icon: LucideIcon;
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
                {items.map((item) => (
                    <Card key={item.title} className="gap-3">
                        <CardHeader>
                            <div className="flex items-start justify-between gap-2">
                                <span className="flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                    <item.icon className="size-4" />
                                </span>
                                <Badge variant="secondary">
                                    Segera hadir · Fase {item.phase}
                                </Badge>
                            </div>
                            <CardTitle className="pt-2">{item.title}</CardTitle>
                            <CardDescription>
                                {item.description}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ))}
            </div>
        </div>
    );
}
