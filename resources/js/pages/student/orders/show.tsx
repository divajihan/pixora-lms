import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CreditCard,
    ExternalLink,
    FileText,
    FlaskConical,
    RefreshCw,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { OrderStatusBadge } from '@/components/orders/order-status-badge';
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
import { useSnap } from '@/hooks/use-snap';
import { formatDateTime, formatDateRange, formatRupiah } from '@/lib/format';
import { course as learn } from '@/routes/learn';
import { dashboard } from '@/routes/student';
import {
    index as ordersIndex,
    invoice,
    reconcile,
    show,
    simulate,
} from '@/routes/student/orders';
import type { OrderData, PaymentClient } from '@/types/orders';

type Props = {
    order: OrderData;
    payment: PaymentClient | null;
    autoPay: boolean;
};

const POLL_INTERVAL_MS = 5000;
const POLL_LIMIT = 24; // ~2 minutes after the last action

export default function StudentOrderShow({ order, payment, autoPay }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const { ready, failed, pay } = useSnap(
        payment?.driver === 'midtrans' ? payment.script_url : null,
        payment?.client_key ?? null,
    );
    const [checking, setChecking] = useState(false);
    const [polls, setPolls] = useState(0);
    const autoPayDone = useRef(false);

    const checkStatus = () => {
        router.post(
            reconcile(order.order_number).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setChecking(true),
                onFinish: () => {
                    setChecking(false);
                    setPolls(0);
                },
            },
        );
    };

    const openSnap = () => {
        if (!payment?.token) {
            return;
        }

        // Snap callbacks only trigger a server-side status check.
        pay(payment.token, {
            onSuccess: checkStatus,
            onPending: checkStatus,
            onError: checkStatus,
            onClose: () => setPolls(0),
        });
    };

    // Light polling while the order is pending (webhooks usually arrive within seconds).
    useEffect(() => {
        if (order.status !== 'pending' || polls >= POLL_LIMIT) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.reload({
                only: ['order', 'payment'],
                onFinish: () => setPolls((count) => count + 1),
            });
        }, POLL_INTERVAL_MS);

        return () => window.clearTimeout(timer);
    }, [order.status, polls]);

    useEffect(() => {
        if (autoPay && ready && payment?.token && !autoPayDone.current) {
            autoPayDone.current = true;
            openSnap();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- open once when Snap is ready
    }, [autoPay, ready, payment?.token]);

    const firstCourse = order.items?.[0];

    return (
        <>
            <Head title={`Pesanan ${order.order_number}`} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">Pesanan</p>
                        <h1 className="font-mono text-xl font-semibold tracking-tight">
                            {order.order_number}
                        </h1>
                    </div>
                    <OrderStatusBadge
                        status={order.status}
                        className="self-start text-sm"
                    />
                </div>

                {order.status === 'paid' && (
                    <Alert>
                        <CheckCircle2 />
                        <AlertTitle>Pembayaran berhasil</AlertTitle>
                        <AlertDescription>
                            <p>Akses kursus sudah aktif. Selamat belajar!</p>
                            {firstCourse?.course_slug && order.has_access && (
                                <Button asChild size="sm" className="mt-2">
                                    <Link href={learn(firstCourse.course_slug)}>
                                        Mulai belajar
                                    </Link>
                                </Button>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {(order.status === 'failed' || order.status === 'expired') && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>
                            {order.status === 'failed'
                                ? 'Pembayaran gagal'
                                : 'Pesanan kedaluwarsa'}
                        </AlertTitle>
                        <AlertDescription>
                            Silakan buat pesanan baru dari halaman kursus.
                            {firstCourse?.course_slug && (
                                <Link
                                    href={`/kursus/${firstCourse.course_slug}`}
                                    className="ml-1 underline"
                                >
                                    Kembali ke kursus
                                </Link>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <Card className="gap-4">
                    <CardHeader>
                        <CardTitle>Rincian</CardTitle>
                        {order.created_at && (
                            <CardDescription>
                                Dibuat {formatDateTime(order.created_at)}
                            </CardDescription>
                        )}
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 text-sm">
                        {order.items?.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-start justify-between gap-4"
                            >
                                <span>
                                    {item.title}
                                    {item.batch && (
                                        <span className="block text-xs text-muted-foreground">
                                            {item.batch.name} ·{' '}
                                            {formatDateRange(
                                                item.batch.starts_on,
                                                item.batch.ends_on,
                                            )}
                                        </span>
                                    )}
                                </span>
                                <span className="shrink-0">
                                    {formatRupiah(item.price)}
                                </span>
                            </div>
                        ))}
                        <div className="flex items-center justify-between border-t pt-3 font-semibold">
                            <span>Total</span>
                            <span>{formatRupiah(order.total)}</span>
                        </div>
                        {order.status === 'pending' && order.expires_at && (
                            <p className="text-xs text-muted-foreground">
                                Bayar sebelum {formatDateTime(order.expires_at)}
                                .
                            </p>
                        )}
                        {order.paid_at && (
                            <p className="text-xs text-muted-foreground">
                                Dibayar {formatDateTime(order.paid_at)}.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {payment && order.status === 'pending' && (
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle>Pembayaran</CardTitle>
                            <CardDescription>
                                Status diperbarui otomatis setelah pembayaran
                                terkonfirmasi oleh penyedia pembayaran.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {payment.driver === 'midtrans' && (
                                <>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            onClick={openSnap}
                                            disabled={!ready || !payment.token}
                                        >
                                            {!ready && !failed ? (
                                                <Spinner />
                                            ) : (
                                                <CreditCard />
                                            )}
                                            Bayar sekarang
                                        </Button>
                                        {payment.redirect_url && (
                                            <Button asChild variant="outline">
                                                <a href={payment.redirect_url}>
                                                    Buka halaman pembayaran
                                                    <ExternalLink />
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                    {failed && (
                                        <p className="text-sm text-destructive">
                                            Gagal memuat Snap. Gunakan tombol
                                            "Buka halaman pembayaran".
                                        </p>
                                    )}
                                </>
                            )}

                            {payment.driver === 'fake' && (
                                <div className="rounded-md border border-dashed p-3">
                                    <p className="mb-2 flex items-center gap-2 text-sm font-medium">
                                        <FlaskConical className="size-4" />
                                        Mode simulasi pembayaran (pengembangan)
                                    </p>
                                    <p className="mb-3 text-xs text-muted-foreground">
                                        PAYMENT_GATEWAY=fake. Tombol ini
                                        mengirim notifikasi bertanda tangan ke
                                        webhook, sama seperti Midtrans.
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {[
                                            ['settlement', 'Bayar berhasil'],
                                            ['pending', 'Pending'],
                                            ['deny', 'Ditolak'],
                                            ['expire', 'Kedaluwarsa'],
                                        ].map(([status, label]) => (
                                            <Button
                                                key={status}
                                                size="sm"
                                                variant={
                                                    status === 'settlement'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    router.post(
                                                        simulate(
                                                            order.order_number,
                                                        ).url,
                                                        { status },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {label}
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={checkStatus}
                                    disabled={checking}
                                >
                                    {checking ? <Spinner /> : <RefreshCw />}
                                    Cek status pembayaran
                                </Button>
                                {polls < POLL_LIMIT && (
                                    <span
                                        className="text-xs text-muted-foreground"
                                        aria-live="polite"
                                    >
                                        Memantau status…
                                    </span>
                                )}
                            </div>
                            {(errors.payment ?? errors.status) && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.payment ?? errors.status}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={ordersIndex()}>Semua pesanan</Link>
                    </Button>
                    {order.status === 'paid' && (
                        <Button asChild variant="outline">
                            <a
                                href={invoice(order.order_number).url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileText />
                                Invoice
                            </a>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

StudentOrderShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Pesanan', href: ordersIndex() },
        {
            title: props.order.order_number,
            href: show(props.order.order_number),
        },
    ],
});
