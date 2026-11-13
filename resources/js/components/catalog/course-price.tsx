import { Badge } from '@/components/ui/badge';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

export function CoursePrice({
    price,
    isFree,
    className,
}: {
    price: number;
    isFree: boolean;
    className?: string;
}) {
    if (isFree) {
        return <Badge className={className}>Gratis</Badge>;
    }

    return (
        <span className={cn('font-semibold text-foreground', className)}>
            {formatRupiah(price)}
        </span>
    );
}
