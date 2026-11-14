import { Head } from '@inertiajs/react';
import { CourseForm } from '@/components/admin/course-form';
import { dashboard } from '@/routes/admin';
import { create, index as coursesIndex } from '@/routes/admin/courses';
import type { CategoryItem } from '@/types/admin';

export default function AdminCourseCreate({
    categories,
}: {
    categories: CategoryItem[];
}) {
    return (
        <>
            <Head title="Kursus baru" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Kursus baru
                    </h1>
                    <p className="text-muted-foreground">
                        Kursus dibuat sebagai draf. Terbitkan setelah kurikulum
                        dan instruktur siap.
                    </p>
                </div>
                <CourseForm categories={categories} />
            </div>
        </>
    );
}

AdminCourseCreate.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus', href: coursesIndex() },
        { title: 'Kursus baru', href: create() },
    ],
};
