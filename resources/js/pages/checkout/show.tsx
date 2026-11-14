import { Form, Link } from '@inertiajs/react';
import { Clock, Lock, ShieldCheck } from 'lucide-react';
import { CoursePrice } from '@/components/catalog/course-price';
import { CourseThumbnail } from '@/components/catalog/course-thumbnail';
import InputError from '@/components/input-error';
import { SeoHead } from '@/components/seo-head';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { formatRupiah } from '@/lib/format';
import { free, store } from '@/routes/checkout';
import { show as courseShow } from '@/routes/courses';

type Props = {
    course: {
        id: number;
        slug: string;
        title: string;
        summary: string | null;
        thumbnail_url: string | null;
        price: number;
        is_free: boolean;
        access_duration_days: number | null;
    };
    buyer: { name: string; email: string };
    expiryHours: number;
};

export default function CheckoutShow({ course, buyer, expiryHours }: Props) {
    return (
        <>
            <SeoHead
                title="Checkout"
                description={`Checkout kursus ${course.title}`}
            />
            <div className="mx-auto grid w-full max-w-4xl gap-6 px-4 py-10 md:grid-cols-[1fr_320px] md:px-6">
                <div className="flex flex-col gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {course.is_free
                                ? 'Ambil kursus gratis'
                                : 'Ringkasan pesanan'}
                        </h1>
                        <p className="text-muted-foreground">
                            Periksa kembali sebelum melanjutkan.
                        </p>
                    </div>
                    <Card className="gap-0 overflow-hidden py-0 sm:flex-row">
                        <CourseThumbnail
                            src={course.thumbnail_url}
                            title={course.title}
                            className="sm:w-56 sm:shrink-0"
                        />
                        <CardContent className="flex flex-col gap-1 p-4">
                            <Link
                                href={courseShow(course.slug)}
                                className="font-semibold hover:underline"
                            >
                                {course.title}
                            </Link>
                            {course.summary && (
                                <p className="line-clamp-2 text-sm text-muted-foreground">
                                    {course.summary}
                                </p>
                            )}
                            <p className="mt-auto flex items-center gap-1 pt-2 text-xs text-muted-foreground">
                                <Clock className="size-3" />
                                {course.access_duration_days
                                    ? `Akses ${course.access_duration_days} hari`
                                    : 'Akses seumur hidup'}
                            </p>
                        </CardContent>
                    </Card>
                    <p className="text-sm text-muted-foreground">
                        Pembeli:{' '}
                        <span className="text-foreground">{buyer.name}</span> (
                        {buyer.email})
                    </p>
                </div>

                <Card className="gap-4 self-start">
                    <CardHeader>
                        <CardTitle>Total</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Harga kursus
                            </span>
                            <CoursePrice
                                price={course.price}
                                isFree={course.is_free}
                            />
                        </div>
                        <Separator />
                        <div className="flex items-center justify-between">
                            <span className="font-medium">Total bayar</span>
                            <span className="text-xl font-semibold">
                                {course.is_free
                                    ? 'Rp 0'
                                    : formatRupiah(course.price)}
                            </span>
                        </div>

                        <Form
                            {...(course.is_free
                                ? free.form(course.slug)
                                : store.form(course.slug))}
                            className="flex flex-col gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {course.is_free
                                            ? 'Ambil gratis'
                                            : 'Lanjut ke pembayaran'}
                                    </Button>
                                    <InputError
                                        message={
                                            errors.course ?? errors.payment
                                        }
                                    />
                                </>
                            )}
                        </Form>

                        {!course.is_free && (
                            <ul className="space-y-1 text-xs text-muted-foreground">
                                <li className="flex gap-2">
                                    <ShieldCheck className="size-4 shrink-0" />
                                    Pembayaran diproses aman oleh Midtrans.
                                </li>
                                <li className="flex gap-2">
                                    <Lock className="size-4 shrink-0" />
                                    Akses kursus aktif otomatis setelah
                                    pembayaran terkonfirmasi.
                                </li>
                                <li className="flex gap-2">
                                    <Clock className="size-4 shrink-0" />
                                    Pesanan berlaku {expiryHours} jam.
                                </li>
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
