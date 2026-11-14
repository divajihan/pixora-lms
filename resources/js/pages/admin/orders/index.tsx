import { Head, Link, router } from '@inertiajs/react';
import { Receipt, Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { Pagination } from '@/components/catalog/pagination';
import { OrderStatusBadge } from '@/components/orders/order-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime, formatRupiah } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { index as ordersIndex, show } from '@/routes/admin/orders';
import type { Option } from '@/types/admin';
import type { Paginated } from '@/types/catalog';
import type { OrderData } from '@/types/orders';

const ALL = 'semua';

export default function AdminOrders({
    orders,
    filters,
    statuses,
}: {
    orders: Paginated<OrderData>;
    filters: { q: string | null; status: string | null };
    statuses: Option[];
}) {
    const [search, setSearch] = useState(filters.q ?? '');
    const [loading, setLoading] = useState(false);

    const apply = (next: Partial<typeof filters>) => {
        const query = Object.fromEntries(
            Object.entries({
                ...filters,
                q: search.trim() || null,
                ...next,
            }).filter(([, value]) => value),
        );

        router.get(ordersIndex().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    const statusLabel =
        statuses.find((status) => status.value === filters.status)?.label ??
        'Semua status';
    const hasFilters = Boolean(filters.q || filters.status);

    return (
        <>
            <Head title="Pesanan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pesanan
                    </h1>
                    <p className="text-muted-foreground">
                        Order dan pembayaran semua siswa.
                    </p>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <form
                        role="search"
                        onSubmit={submit}
                        className="flex flex-1 gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nomor pesanan atau email…"
                                aria-label="Cari nomor pesanan atau email"
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            Cari
                        </Button>
                    </form>
                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) =>
                            apply({ status: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger
                            aria-label="Filter status"
                            className="w-full sm:w-56"
                        >
                            <SelectValue>{statusLabel}</SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua status</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span aria-live="polite">
                        {loading ? 'Memuat…' : `${orders.total} pesanan`}
                    </span>
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                router.get(ordersIndex().url);
                            }}
                        >
                            <X />
                            Hapus filter
                        </Button>
                    )}
                </div>

                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title={
                            hasFilters
                                ? 'Tidak ada pesanan yang cocok'
                                : 'Belum ada pesanan'
                        }
                    />
                ) : (
                    <div
                        className={`overflow-x-auto rounded-lg border transition-opacity ${loading ? 'opacity-60' : ''}`}
                    >
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Pesanan
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Pembeli
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Total
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {orders.data.map((order) => (
                                    <tr key={order.id} className="align-top">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={show(order.order_number)}
                                                className="font-mono font-medium hover:text-primary hover:underline"
                                            >
                                                {order.order_number}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {order.items
                                                    ?.map((item) => item.title)
                                                    .join(', ')}
                                            </p>
                                            {order.created_at && (
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        order.created_at,
                                                    )}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p>{order.customer?.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {order.customer?.email}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {formatRupiah(order.total)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <OrderStatusBadge
                                                status={order.status}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={orders.links} />
            </div>
        </>
    );
}

AdminOrders.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Pesanan', href: ordersIndex() },
    ],
};
