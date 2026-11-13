import { Head, Link, router } from '@inertiajs/react';
import { ListTree, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { CourseForm } from '@/components/admin/course-form';
import { CourseInstructorsCard } from '@/components/admin/course-instructors-card';
import { CourseStatusCard } from '@/components/admin/course-status-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import {
    curriculum,
    destroy,
    edit,
    index as coursesIndex,
} from '@/routes/admin/courses';
import type {
    AdminCourse,
    AssignedInstructor,
    CategoryItem,
    CourseStatusValue,
    InstructorCandidate,
    Option,
} from '@/types/admin';

type Props = {
    course: AdminCourse;
    categories: CategoryItem[];
    statusTargets: Option<CourseStatusValue>[];
    publishBlockers: string[];
    instructors: AssignedInstructor[];
    availableInstructors: InstructorCandidate[];
    instructorRoles: Option<'lead' | 'assistant'>[];
};

export default function AdminCourseEdit({
    course,
    categories,
    statusTargets,
    publishBlockers,
    instructors,
    availableInstructors,
    instructorRoles,
}: Props) {
    const [deleting, setDeleting] = useState(false);

    return (
        <>
            <Head title={`Ubah: ${course.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <h1 className="truncate text-2xl font-semibold tracking-tight">
                            {course.title}
                        </h1>
                        <p className="text-muted-foreground">
                            {course.modules_count ?? 0} modul ·{' '}
                            {course.lessons_count ?? 0} lesson
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={curriculum(course.id)}>
                            <ListTree />
                            Kelola kurikulum
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
                    <CourseForm course={course} categories={categories} />

                    <div className="flex flex-col gap-6">
                        <CourseStatusCard
                            course={course}
                            targets={statusTargets}
                            blockers={publishBlockers}
                        />
                        <CourseInstructorsCard
                            courseId={course.id}
                            instructors={instructors}
                            available={availableInstructors}
                            roles={instructorRoles}
                        />
                        <Card className="gap-4 border-destructive/40">
                            <CardHeader>
                                <CardTitle>Hapus kursus</CardTitle>
                                <CardDescription>
                                    Kursus dipindahkan ke tempat sampah (soft
                                    delete) dan hilang dari katalog.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="destructive" size="sm">
                                            <Trash2 />
                                            Hapus kursus
                                        </Button>
                                    }
                                    title="Hapus kursus ini?"
                                    description={`"${course.title}" akan dihapus dari daftar dan katalog publik.`}
                                    processing={deleting}
                                    onConfirm={() =>
                                        router.delete(destroy(course.id).url, {
                                            onStart: () => setDeleting(true),
                                            onFinish: () => setDeleting(false),
                                        })
                                    }
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

AdminCourseEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus', href: coursesIndex() },
        { title: props.course.title, href: edit(props.course.id) },
    ],
});
