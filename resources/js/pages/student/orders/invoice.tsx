import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateRange, formatRupiah } from '@/lib/format';
import type { OrderData } from '@/types/orders';

type Props = {
    order: OrderData;
    seller: { name: string; url: string };
};

/**
 * Printable invoice (no app chrome). Uses the browser's "Print / Save as PDF".
 */
export default function OrderInvoice({ order, seller }: Props) {
    const paidPayment = order.payments?.find(
        (payment) => payment.status === 'success',
    );

    return (
        <>
            <Head title={`Invoice ${order.order_number}`} />
            <div className="min-h-svh bg-muted/40 py-8 print:bg-white print:py-0">
                <div className="mx-auto mb-4 flex max-w-2xl justify-end px-4 print:hidden">
                    <Button onClick={() => window.print()}>
                        <Printer />
                        Cetak / simpan PDF
                    </Button>
                </div>
                <article className="mx-auto max-w-2xl bg-background p-8 shadow-sm print:max-w-none print:p-0 print:shadow-none">
                    <header className="flex items-start justify-between gap-4 border-b pb-6">
                        <div>
                            <p className="text-xl font-semibold">
                                {seller.name}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {seller.url}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-2xl font-semibold tracking-tight">
                                INVOICE
                            </p>
                            <p className="font-mono text-sm">
                                {order.order_number}
                            </p>
                        </div>
                    </header>

                    <section className="grid grid-cols-2 gap-4 py-6 text-sm">
                        <div>
                            <p className="text-muted-foreground">
                                Ditagihkan kepada
                            </p>
                            <p className="font-medium">
                                {order.customer?.name}
                            </p>
                            <p>{order.customer?.email}</p>
                        </div>
                        <div className="text-right">
                            <p>
                                <span className="text-muted-foreground">
                                    Tanggal pesanan:
                                </span>{' '}
                                {order.created_at
                                    ? formatDate(order.created_at)
                                    : '–'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">
                                    Tanggal bayar:
                                </span>{' '}
                                {order.paid_at
                                    ? formatDate(order.paid_at)
                                    : '–'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">
                                    Status:
                                </span>{' '}
                                {order.status_label}
                            </p>
                            {paidPayment?.payment_type && (
                                <p>
                                    <span className="text-muted-foreground">
                                        Metode:
                                    </span>{' '}
                                    {paidPayment.payment_type.replaceAll(
                                        '_',
                                        ' ',
                                    )}
                                </p>
                            )}
                        </div>
                    </section>

                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-y text-left text-muted-foreground">
                                <th className="py-2 font-medium">Deskripsi</th>
                                <th className="py-2 text-right font-medium">
                                    Jumlah
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {order.items?.map((item) => (
                                <tr key={item.id} className="border-b">
                                    <td className="py-3">
                                        Kursus online: {item.title}
                                        {item.batch && (
                                            <span className="block text-xs text-muted-foreground">
                                                {item.batch.name} ·{' '}
                                                {formatDateRange(
                                                    item.batch.starts_on,
                                                    item.batch.ends_on,
                                                )}
                                            </span>
                                        )}
                                    </td>
                                    <td className="py-3 text-right">
                                        {formatRupiah(item.price)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            {order.discount > 0 && (
                                <tr>
                                    <td className="pt-3 text-right text-muted-foreground">
                                        Diskon
                                    </td>
                                    <td className="pt-3 text-right">
                                        −{formatRupiah(order.discount)}
                                    </td>
                                </tr>
                            )}
                            <tr>
                                <td className="pt-3 text-right font-semibold">
                                    Total
                                </td>
                                <td className="pt-3 text-right text-lg font-semibold">
                                    {formatRupiah(order.total)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <p className="mt-10 text-xs text-muted-foreground">
                        Invoice ini dibuat otomatis oleh sistem dan sah tanpa
                        tanda tangan.
                    </p>
                </article>
            </div>
        </>
    );
}
