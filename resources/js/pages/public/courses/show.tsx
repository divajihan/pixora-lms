import { Link } from '@inertiajs/react';
import { Award, ChevronRight, Clock, ListChecks } from 'lucide-react';
import { CoursePrice } from '@/components/catalog/course-price';
import { CourseThumbnail } from '@/components/catalog/course-thumbnail';
import { PublicCurriculum } from '@/components/catalog/public-curriculum';
import { PurchaseCta } from '@/components/catalog/purchase-cta';
import { formatDuration } from '@/lib/format';
import { SeoHead } from '@/components/seo-head';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useInitials } from '@/hooks/use-initials';
import { index as catalog } from '@/routes/courses';
import type {
    CourseDetail,
    PageMeta,
    PublicCurriculum as PublicCurriculumData,
} from '@/types/catalog';
import type { PurchaseInfo } from '@/types/orders';

function accessLabel(days: number | null): string {
    return days ? `Akses ${days} hari` : 'Akses seumur hidup';
}

export default function CourseShow({
    meta,
    course,
    curriculum,
    purchase,
}: {
    meta: PageMeta;
    course: CourseDetail;
    curriculum: PublicCurriculumData;
    purchase: PurchaseInfo;
}) {
    const getInitials = useInitials();

    return (
        <>
            <SeoHead title={meta.title} description={meta.description} />

            <section className="border-b bg-muted/30">
                <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-8 md:px-6 md:py-12 lg:grid-cols-[1fr_360px]">
                    <div className="flex flex-col gap-4">
                        <nav
                            aria-label="Breadcrumb"
                            className="flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
                        >
                            <Link
                                href={catalog()}
                                className="hover:text-foreground"
                            >
                                Katalog
                            </Link>
                            {course.category && (
                                <>
                                    <ChevronRight className="size-4" />
                                    <Link
                                        href={catalog({
                                            query: {
                                                kategori: course.category.slug,
                                            },
                                        })}
                                        className="hover:text-foreground"
                                    >
                                        {course.category.name}
                                    </Link>
                                </>
                            )}
                        </nav>

                        <h1 className="text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                            {course.title}
                        </h1>
                        {course.summary && (
                            <p className="text-lg text-muted-foreground">
                                {course.summary}
                            </p>
                        )}
                        {course.instructors.length > 0 && (
                            <p className="text-sm text-muted-foreground">
                                Bersama{' '}
                                <span className="font-medium text-foreground">
                                    {course.instructors
                                        .map((instructor) => instructor.name)
                                        .join(', ')}
                                </span>
                            </p>
                        )}
                    </div>

                    {/* Purchase card: CTA state comes from the server (PurchaseState). */}
                    <Card className="gap-0 self-start overflow-hidden py-0 lg:row-span-2">
                        <CourseThumbnail
                            src={course.thumbnail_url}
                            title={course.title}
                            className="border-b"
                        />
                        <CardContent className="flex flex-col gap-4 p-5">
                            <CoursePrice
                                price={course.price}
                                isFree={course.is_free}
                                className="self-start text-2xl"
                            />
                            <PurchaseCta
                                purchase={purchase}
                                slug={course.slug}
                            />
                            <Separator />
                            <ul className="flex flex-col gap-2 text-sm">
                                {curriculum.lesson_count > 0 && (
                                    <li className="flex items-center gap-2">
                                        <ListChecks className="size-4 text-muted-foreground" />
                                        {curriculum.lesson_count} lesson ·{' '}
                                        {formatDuration(
                                            curriculum.duration_seconds,
                                        )}
                                    </li>
                                )}
                                <li className="flex items-center gap-2">
                                    <Clock className="size-4 text-muted-foreground" />
                                    {accessLabel(course.access_duration_days)}
                                </li>
                                {course.certificate_enabled && (
                                    <li className="flex items-center gap-2">
                                        <Award className="size-4 text-muted-foreground" />
                                        Sertifikat setelah lulus
                                    </li>
                                )}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-10 md:px-6 lg:grid-cols-[1fr_360px]">
                <div className="flex flex-col gap-10">
                    <section aria-labelledby="about-heading">
                        <h2
                            id="about-heading"
                            className="mb-3 text-xl font-semibold"
                        >
                            Tentang kursus
                        </h2>
                        {course.description ? (
                            <p className="leading-relaxed whitespace-pre-line text-muted-foreground">
                                {course.description}
                            </p>
                        ) : (
                            <p className="text-muted-foreground">
                                Deskripsi kursus belum tersedia.
                            </p>
                        )}
                    </section>

                    <section aria-labelledby="curriculum-heading">
                        <h2
                            id="curriculum-heading"
                            className="mb-3 text-xl font-semibold"
                        >
                            Kurikulum
                        </h2>
                        <PublicCurriculum curriculum={curriculum} />
                    </section>

                    {course.instructors.length > 0 && (
                        <section aria-labelledby="instructor-heading">
                            <h2
                                id="instructor-heading"
                                className="mb-4 text-xl font-semibold"
                            >
                                Instruktur
                            </h2>
                            <div className="flex flex-col gap-6">
                                {course.instructors.map((instructor) => (
                                    <div
                                        key={instructor.id}
                                        className="flex gap-4"
                                    >
                                        <Avatar className="size-12">
                                            {instructor.avatar_url && (
                                                <AvatarImage
                                                    src={instructor.avatar_url}
                                                    alt={instructor.name}
                                                />
                                            )}
                                            <AvatarFallback>
                                                {getInitials(instructor.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="flex flex-col gap-1">
                                            <p className="font-medium">
                                                {instructor.name}
                                                {instructor.role && (
                                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                        {instructor.role}
                                                    </span>
                                                )}
                                            </p>
                                            {instructor.headline && (
                                                <p className="text-sm text-muted-foreground">
                                                    {instructor.headline}
                                                </p>
                                            )}
                                            {instructor.bio && (
                                                <p className="text-sm leading-relaxed">
                                                    {instructor.bio}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}
