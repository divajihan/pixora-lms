import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { CourseStatusValue, LessonStatusValue } from '@/types/admin';

const styles: Record<CourseStatusValue | LessonStatusValue, string> = {
    draft: 'border-dashed bg-transparent text-muted-foreground',
    published: '',
    archived: 'bg-muted text-muted-foreground',
};

const labels: Record<CourseStatusValue | LessonStatusValue, string> = {
    draft: 'Draf',
    published: 'Terbit',
    archived: 'Diarsipkan',
};

export function StatusBadge({
    status,
    className,
}: {
    status: CourseStatusValue | LessonStatusValue;
    className?: string;
}) {
    return (
        <Badge
            variant={status === 'published' ? 'default' : 'outline'}
            className={cn(styles[status], className)}
        >
            {labels[status]}
        </Badge>
    );
}
