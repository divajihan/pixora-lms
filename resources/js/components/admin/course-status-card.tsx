import { router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/admin/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { show as publicShow } from '@/routes/courses';
import { status as statusRoute } from '@/routes/admin/courses';
import type { AdminCourse, CourseStatusValue, Option } from '@/types/admin';

const actionLabels: Record<CourseStatusValue, string> = {
    published: 'Terbitkan',
    draft: 'Jadikan draf',
    archived: 'Arsipkan',
};

export function CourseStatusCard({
    course,
    targets,
    blockers,
}: {
    course: AdminCourse;
    targets: Option<CourseStatusValue>[];
    blockers: string[];
}) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [pending, setPending] = useState<CourseStatusValue | null>(null);

    const change = (target: CourseStatusValue) => {
        router.patch(
            statusRoute(course.id).url,
            { status: target },
            {
                preserveScroll: true,
                onStart: () => setPending(target),
                onFinish: () => setPending(null),
            },
        );
    };

    const canPublish = blockers.length === 0;

    return (
        <Card className="gap-4">
            <CardHeader>
                <div className="flex items-center justify-between gap-2">
                    <CardTitle>Status</CardTitle>
                    <StatusBadge status={course.status} />
                </div>
                <CardDescription>
                    {course.status === 'published' && course.published_at
                        ? `Terbit sejak ${formatDate(course.published_at)}.`
                        : course.status === 'archived'
                          ? 'Tidak tampil di katalog publik.'
                          : 'Belum tampil di katalog publik.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {course.status !== 'published' &&
                    (canPublish ? (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <CheckCircle2 className="size-4 text-primary" />
                            Siap diterbitkan.
                        </p>
                    ) : (
                        <Alert>
                            <AlertTriangle />
                            <AlertTitle>Belum bisa diterbitkan</AlertTitle>
                            <AlertDescription>
                                <ul className="list-disc pl-4">
                                    {blockers.map((reason) => (
                                        <li key={reason}>{reason}</li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    ))}

                {errors.status && (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.status}
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    {targets.map((target) => (
                        <Button
                            key={target.value}
                            type="button"
                            size="sm"
                            variant={
                                target.value === 'published'
                                    ? 'default'
                                    : 'outline'
                            }
                            disabled={
                                pending !== null ||
                                (target.value === 'published' && !canPublish)
                            }
                            onClick={() => change(target.value)}
                        >
                            {pending === target.value && <Spinner />}
                            {actionLabels[target.value]}
                        </Button>
                    ))}
                </div>

                {course.status === 'published' && (
                    <Button
                        asChild
                        variant="link"
                        size="sm"
                        className="justify-start px-0"
                    >
                        <a
                            href={publicShow(course.slug).url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            Lihat halaman publik
                            <ExternalLink />
                        </a>
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
