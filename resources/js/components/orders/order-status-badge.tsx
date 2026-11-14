import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { OrderStatusValue } from '@/types/orders';

const labels: Record<OrderStatusValue, string> = {
    pending: 'Menunggu pembayaran',
    paid: 'Lunas',
    failed: 'Gagal',
    expired: 'Kedaluwarsa',
    refunded: 'Dikembalikan',
};

export function OrderStatusBadge({
    status,
    className,
}: {
    status: OrderStatusValue;
    className?: string;
}) {
    return (
        <Badge
            variant={
                status === 'paid'
                    ? 'default'
                    : status === 'pending'
                      ? 'secondary'
                      : 'outline'
            }
            className={cn(
                status === 'failed' && 'text-destructive',
                status === 'expired' && 'text-muted-foreground',
                className,
            )}
        >
            {labels[status]}
        </Badge>
    );
}
