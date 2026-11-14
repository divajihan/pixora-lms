import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, BadgeCheck, RefreshCw, Undo2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { OrderStatusBadge } from '@/components/orders/order-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, formatRupiah } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import {
    index as ordersIndex,
    markPaid,
    reconcile,
    refund,
    show,
} from '@/routes/admin/orders';
import type { OrderData } from '@/types/orders';

type Props = {
    order: OrderData;
    can: { markPaid: boolean; refund: boolean };
    gateway: string;
};

function ActionDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    destructive,
    action,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    action: { url: string; method: 'post' };
    children?: React.ReactNode;
}) {
    const form = useForm({ note: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(action, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {children}
                    <div className="grid gap-2">
                        <Label htmlFor="action-note">Catatan (wajib)</Label>
                        <Textarea
                            id="action-note"
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                            rows={3}
                            maxLength={1000}
                            placeholder="Contoh: transfer BCA a.n. Budi, 14 Nov 2026, ref 12345"
                            aria-invalid={Boolean(form.errors.note)}
                        />
                        <InputError message={form.errors.note} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {confirmLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminOrderShow({ order, can, gateway }: Props) {
    const [dialog, setDialog] = useState<'paid' | 'refund' | null>(null);
    const [checking, setChecking] = useState(false);

    return (
        <>
            <Head title={`Pesanan ${order.order_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="font-mono text-xl font-semibold tracking-tight">
                                {order.order_number}
                            </h1>
                            <OrderStatusBadge status={order.status} />
                        </div>
                        <p className="text-muted-foreground">
                            {order.customer?.name} · {order.customer?.email}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            disabled={checking}
                            onClick={() =>
                                router.post(
                                    reconcile(order.order_number).url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onStart: () => setChecking(true),
                                        onFinish: () => setChecking(false),
                                    },
                                )
                            }
                        >
                            {checking ? <Spinner /> : <RefreshCw />}
                            Cek status ke gateway
                        </Button>
                        {can.markPaid && (
                            <Button onClick={() => setDialog('paid')}>
                                <BadgeCheck />
                                Tandai lunas manual
                            </Button>
                        )}
                        {can.refund && (
                            <Button
                                variant="destructive"
                                onClick={() => setDialog('refund')}
                            >
                                <Undo2 />
                                Refund & cabut akses
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle>Item</CardTitle>
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
                                    className="flex justify-between gap-4"
                                >
                                    <span>{item.title}</span>
                                    <span className="shrink-0">
                                        {formatRupiah(item.price)}
                                    </span>
                                </div>
                            ))}
                            <div className="flex justify-between border-t pt-3 font-semibold">
                                <span>Total</span>
                                <span>{formatRupiah(order.total)}</span>
                            </div>
                            <dl className="grid grid-cols-2 gap-1 text-xs text-muted-foreground">
                                <dt>Kedaluwarsa</dt>
                                <dd>
                                    {order.expires_at
                                        ? formatDateTime(order.expires_at)
                                        : '–'}
                                </dd>
                                <dt>Dibayar</dt>
                                <dd>
                                    {order.paid_at
                                        ? formatDateTime(order.paid_at)
                                        : '–'}
                                </dd>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle>Akses kursus</CardTitle>
                            <CardDescription>
                                Enrollment yang dibuat dari pesanan ini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {order.enrollments &&
                            order.enrollments.length > 0 ? (
                                <ul className="space-y-2">
                                    {order.enrollments.map((enrollment) => (
                                        <li
                                            key={enrollment.id}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <span>
                                                {order.items?.find(
                                                    (item) =>
                                                        item.course_id ===
                                                        enrollment.course_id,
                                                )?.title ??
                                                    `Kursus #${enrollment.course_id}`}
                                            </span>
                                            <Badge
                                                variant={
                                                    enrollment.is_accessible
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {enrollment.status_label}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-muted-foreground">
                                    Belum ada enrollment.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-4">
                    <CardHeader>
                        <CardTitle>Riwayat pembayaran</CardTitle>
                        <CardDescription>
                            Percobaan pembayaran dari gateway dan tindakan
                            manual admin.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {order.payments && order.payments.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[560px] text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr>
                                            <th className="py-2 font-medium">
                                                Waktu
                                            </th>
                                            <th className="py-2 font-medium">
                                                Sumber
                                            </th>
                                            <th className="py-2 font-medium">
                                                Metode
                                            </th>
                                            <th className="py-2 font-medium">
                                                Status
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Jumlah
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {order.payments.map((payment) => (
                                            <tr
                                                key={payment.id}
                                                className="align-top"
                                            >
                                                <td className="py-2">
                                                    {payment.updated_at
                                                        ? formatDateTime(
                                                              payment.updated_at,
                                                          )
                                                        : '–'}
                                                </td>
                                                <td className="py-2">
                                                    {payment.gateway ===
                                                    'manual'
                                                        ? `Manual (${payment.admin_name ?? 'admin'})`
                                                        : payment.gateway}
                                                    <p
                                                        className="max-w-56 truncate font-mono text-xs text-muted-foreground"
                                                        title={
                                                            payment.external_id
                                                        }
                                                    >
                                                        {payment.external_id}
                                                    </p>
                                                    {payment.note && (
                                                        <p className="text-xs">
                                                            “{payment.note}”
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="py-2">
                                                    {payment.payment_type?.replaceAll(
                                                        '_',
                                                        ' ',
                                                    ) ?? '–'}
                                                </td>
                                                <td className="py-2">
                                                    {payment.status_label}
                                                    {payment.raw_status && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {payment.raw_status}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="py-2 text-right">
                                                    {formatRupiah(
                                                        payment.gross_amount,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Belum ada notifikasi pembayaran.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <ActionDialog
                open={dialog === 'paid'}
                onOpenChange={(open) => !open && setDialog(null)}
                title="Tandai lunas manual?"
                description="Gunakan untuk transfer manual atau bila webhook gagal. Akses kursus langsung diberikan dan email konfirmasi dikirim."
                confirmLabel="Tandai lunas"
                action={markPaid(order.order_number)}
            />
            <ActionDialog
                open={dialog === 'refund'}
                onOpenChange={(open) => !open && setDialog(null)}
                title="Refund & cabut akses?"
                description="Status pesanan menjadi Dikembalikan dan akses kursus dicabut."
                confirmLabel="Refund & cabut akses"
                destructive
                action={refund(order.order_number)}
            >
                <Alert>
                    <AlertTriangle />
                    <AlertTitle>Pengembalian dana dilakukan manual</AlertTitle>
                    <AlertDescription>
                        {gateway === 'midtrans'
                            ? 'Tindakan ini hanya mencatat refund di Pixora. Kembalikan dananya melalui dashboard Midtrans (Transactions → Refund) atau transfer manual.'
                            : 'Tindakan ini hanya mencatat refund di Pixora. Kembalikan dananya secara manual.'}
                    </AlertDescription>
                </Alert>
            </ActionDialog>
        </>
    );
}

AdminOrderShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Pesanan', href: ordersIndex() },
        {
            title: props.order.order_number,
            href: show(props.order.order_number),
        },
    ],
});
