import { Head, Link } from '@inertiajs/react';
import { BookOpen, ChevronRight } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { StatusBadge } from '@/components/admin/status-badge';
import { dashboard } from '@/routes/instructor';
import { index as myCourses, show } from '@/routes/instructor/courses';
import type { CourseStatusValue } from '@/types/admin';

type AssignedCourse = {
    id: number;
    title: string;
    slug: string;
    status: CourseStatusValue;
    status_label: string;
    category: string | null;
    role: string | null;
    modules_count: number;
    lessons_count: number;
    is_public: boolean;
};

export default function InstructorCourses({
    courses,
}: {
    courses: AssignedCourse[];
}) {
    return (
        <>
            <Head title="Kursus saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Kursus saya
                    </h1>
                    <p className="text-muted-foreground">
                        Kursus yang ditugaskan kepada Anda. Konten dikelola oleh
                        admin.
                    </p>
                </div>

                {courses.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title="Belum ada kursus yang ditugaskan"
                        description="Admin akan menugaskan Anda ke kursus. Hubungi admin jika ini keliru."
                    />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {courses.map((course) => (
                            <li key={course.id}>
                                <Link
                                    href={show(course.id)}
                                    className="group flex h-full flex-col gap-2 rounded-lg border bg-card p-4 transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <StatusBadge status={course.status} />
                                        <ChevronRight className="size-4 text-muted-foreground group-hover:text-foreground" />
                                    </div>
                                    <p className="font-semibold group-hover:text-primary">
                                        {course.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {course.category ?? 'Tanpa kategori'} ·{' '}
                                        {course.role ?? 'Instruktur'}
                                    </p>
                                    <p className="mt-auto pt-2 text-xs text-muted-foreground">
                                        {course.modules_count} modul ·{' '}
                                        {course.lessons_count} lesson
                                    </p>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

InstructorCourses.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus saya', href: myCourses() },
    ],
};
