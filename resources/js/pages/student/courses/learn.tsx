import { Head, Link } from '@inertiajs/react';
import { PlayCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { show as courseShow } from '@/routes/courses';
import { dashboard } from '@/routes/student';
import { index as myCourses, learn } from '@/routes/student/courses';

type Props = {
    course: { title: string; slug: string };
    enrollment: { enrolled_at: string; expires_at: string | null };
};

/**
 * Learning area placeholder. The lesson player, drip and progress arrive in Phase 4.
 */
export default function StudentLearn({ course, enrollment }: Props) {
    return (
        <>
            <Head title={`Belajar: ${course.title}`} />
            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <PlayCircle className="size-7" />
                </span>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {course.title}
                    </h1>
                    <p className="text-muted-foreground">
                        Anda terdaftar sejak{' '}
                        {formatDate(enrollment.enrolled_at)}
                        {enrollment.expires_at
                            ? `, akses hingga ${formatDate(enrollment.expires_at)}`
                            : ' dengan akses seumur hidup'}
                        .
                    </p>
                </div>
                <p className="max-w-md rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                    Ruang belajar (pemutar lesson dan progres) segera hadir.
                    Sementara itu, lihat kurikulum di halaman kursus.
                </p>
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={myCourses()}>Kursus saya</Link>
                    </Button>
                    <Button asChild>
                        <Link href={courseShow(course.slug)}>
                            Lihat kurikulum
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

StudentLearn.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus saya', href: myCourses() },
        { title: props.course.title, href: learn(props.course.slug) },
    ],
});
