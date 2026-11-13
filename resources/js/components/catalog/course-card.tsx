import { Link } from '@inertiajs/react';
import { CoursePrice } from '@/components/catalog/course-price';
import { CourseThumbnail } from '@/components/catalog/course-thumbnail';
import { show } from '@/routes/courses';
import type { CourseCard as CourseCardData } from '@/types/catalog';

export function CourseCard({ course }: { course: CourseCardData }) {
    return (
        <Link
            href={show(course.slug)}
            prefetch
            className="group flex flex-col overflow-hidden rounded-lg border bg-card text-card-foreground transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <CourseThumbnail
                src={course.thumbnail_url}
                title={course.title}
                className="border-b"
            />
            <div className="flex flex-1 flex-col gap-2 p-4">
                {course.category && (
                    <span className="text-xs font-medium tracking-wide text-primary uppercase">
                        {course.category.name}
                    </span>
                )}
                <h3 className="leading-snug font-semibold group-hover:text-primary">
                    {course.title}
                </h3>
                {course.summary && (
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {course.summary}
                    </p>
                )}
                <div className="mt-auto flex items-center justify-between gap-2 pt-3">
                    <span className="truncate text-sm text-muted-foreground">
                        {course.instructors.join(', ')}
                    </span>
                    <CoursePrice
                        price={course.price}
                        isFree={course.is_free}
                        className="shrink-0"
                    />
                </div>
            </div>
        </Link>
    );
}
