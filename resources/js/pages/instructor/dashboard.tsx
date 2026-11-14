import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, ClipboardCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes/instructor';
import { index as myCourses } from '@/routes/instructor/courses';
import { show as submissionShow } from '@/routes/submissions';

type ReviewItem = {
    id: number;
    student: string;
    assignment: string;
    course: string | null;
    batch: string | null;
    due_at: string;
    submitted_at: string;
};

type Props = {
    toReview: { count: number; items: ReviewItem[] };
};

export default function InstructorDashboard({ toReview }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dasbor Instruktur" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Halo, {auth.user.name}
                    </h1>
                    <p className="text-muted-foreground">
                        Kursus yang Anda ajar, kelas, dan tugas yang menunggu
                        feedback.
                    </p>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <section
                        aria-labelledby="to-review"
                        className="flex flex-col gap-3 rounded-lg border bg-card p-4"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <h2
                                id="to-review"
                                className="flex items-center gap-2 font-semibold"
                            >
                                <ClipboardCheck className="size-5 text-primary" />
                                Perlu direview
                            </h2>
                            <Badge
                                variant={
                                    toReview.count > 0 ? 'default' : 'secondary'
                                }
                            >
                                {toReview.count}
                            </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Pengumpulan tepat waktu yang tenggatnya sudah lewat
                            dan belum Anda beri feedback.
                        </p>
                        {toReview.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Tidak ada pengumpulan yang menunggu. 🎉
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border">
                                {toReview.items.map((item) => (
                                    <li key={item.id}>
                                        <Link
                                            href={submissionShow(item.id)}
                                            className="flex items-center gap-3 p-3 hover:bg-accent/50"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">
                                                    {item.student} ·{' '}
                                                    {item.assignment}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {item.course}
                                                    {item.batch &&
                                                        ` · ${item.batch}`}{' '}
                                                    · tenggat{' '}
                                                    {formatDateTime(
                                                        item.due_at,
                                                    )}{' '}
                                                    WIB
                                                </p>
                                            </div>
                                            <span className="text-xs text-primary">
                                                Review
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {toReview.count > toReview.items.length && (
                            <p className="text-xs text-muted-foreground">
                                +{toReview.count - toReview.items.length}{' '}
                                lainnya — buka Kursus saya → Tugas untuk melihat
                                semuanya.
                            </p>
                        )}
                    </section>

                    <section className="flex flex-col gap-3 rounded-lg border bg-card p-4">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <BookOpen className="size-5 text-primary" />
                            Kursus saya
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Kurikulum, kelas per batch, jadwal lesson, dan
                            tugas.
                        </p>
                        <Button
                            asChild
                            variant="outline"
                            className="self-start"
                        >
                            <Link href={myCourses()}>Buka kursus saya</Link>
                        </Button>
                    </section>
                </div>
            </div>
        </>
    );
}

InstructorDashboard.layout = {
    breadcrumbs: [{ title: 'Dasbor', href: dashboard() }],
};
