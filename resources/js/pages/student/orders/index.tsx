import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Receipt } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { Pagination } from '@/components/catalog/pagination';
import { OrderStatusBadge } from '@/components/orders/order-status-badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatRupiah } from '@/lib/format';
import { index as catalog } from '@/routes/courses';
import { dashboard } from '@/routes/student';
import { index as ordersIndex, show } from '@/routes/student/orders';
import type { Paginated } from '@/types/catalog';
import type { OrderData } from '@/types/orders';

export default function StudentOrders({
    orders,
}: {
    orders: Paginated<OrderData>;
}) {
    return (
        <>
            <Head title="Pesanan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Riwayat pesanan
                    </h1>
                    <p className="text-muted-foreground">
                        Status pembayaran dan invoice kursus yang Anda beli.
                    </p>
                </div>

                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title="Belum ada pesanan"
                        description="Pesanan kursus berbayar akan muncul di sini."
                        action={
                            <Button asChild size="sm">
                                <Link href={catalog()}>Jelajahi katalog</Link>
                            </Button>
                        }
                    />
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {orders.data.map((order) => (
                            <li key={order.id}>
                                <Link
                                    href={show(order.order_number)}
                                    className="flex items-center gap-3 p-4 hover:bg-accent/50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {order.items
                                                ?.map((item) => item.title)
                                                .join(', ')}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            <span className="font-mono">
                                                {order.order_number}
                                            </span>
                                            {order.created_at &&
                                                ` · ${formatDateTime(order.created_at)}`}
                                        </p>
                                    </div>
                                    <div className="flex flex-col items-end gap-1">
                                        <span className="text-sm font-semibold">
                                            {formatRupiah(order.total)}
                                        </span>
                                        <OrderStatusBadge
                                            status={order.status}
                                        />
                                    </div>
                                    <ChevronRight className="size-4 text-muted-foreground" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination links={orders.links} />
            </div>
        </>
    );
}

StudentOrders.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Pesanan', href: ordersIndex() },
    ],
};
