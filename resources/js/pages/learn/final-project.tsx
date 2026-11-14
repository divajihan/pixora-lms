import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Lock } from 'lucide-react';
import { AssignmentPanel } from '@/components/assignments/assignment-panel';
import { Button } from '@/components/ui/button';
import { formatUnlock } from '@/lib/format';
import { course as courseRoute, finalProject } from '@/routes/learn';
import { dashboard } from '@/routes/student';
import type { AssignmentPanelData } from '@/types/assignments';

type Props = {
    course: { title: string; slug: string };
    title: string;
    /** Set when the final project is not open yet (the batch has not started). */
    opensAt: string | null;
    panel: AssignmentPanelData | null;
};

export default function FinalProject({ course, title, opensAt, panel }: Props) {
    return (
        <>
            <Head title={title} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4 md:p-6">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="self-start"
                >
                    <Link href={courseRoute(course.slug)}>
                        <ChevronLeft />
                        {course.title}
                    </Link>
                </Button>

                {panel ? (
                    <AssignmentPanel panel={panel} courseSlug={course.slug} />
                ) : (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center">
                        <Lock className="size-6 text-muted-foreground" />
                        <p className="font-medium">{title}</p>
                        {opensAt && (
                            <p className="text-sm text-muted-foreground">
                                Terbuka {formatUnlock(opensAt)}.
                            </p>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

FinalProject.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: props.course.title, href: courseRoute(props.course.slug) },
        { title: 'Proyek akhir', href: finalProject(props.course.slug) },
    ],
});
