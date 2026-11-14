import { CalendarDays, Users } from 'lucide-react';
import { formatDateRange } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BatchOption } from '@/types/learning';

/**
 * Batch (cohort) choice for drip courses. Closed batches are shown but not
 * selectable; the server re-validates the choice at checkout.
 */
export function BatchPicker({
    batches,
    value,
    onChange,
    name,
}: {
    batches: BatchOption[];
    value: number | null;
    onChange: (id: number) => void;
    /** When set, renders native radios so a <Form> submits the value. */
    name?: string;
}) {
    if (batches.length === 0) {
        return (
            <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                Belum ada batch yang dibuka. Silakan cek kembali nanti.
            </p>
        );
    }

    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="mb-1 text-sm font-medium">Pilih batch</legend>
            {batches.map((batch) => {
                const selected = value === batch.id;

                return (
                    <label
                        key={batch.id}
                        className={cn(
                            'flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm transition-colors',
                            selected && 'border-primary bg-primary/5',
                            !batch.is_open && 'cursor-not-allowed opacity-60',
                        )}
                    >
                        <input
                            type="radio"
                            name={name ?? 'batch-choice'}
                            value={batch.id}
                            checked={selected}
                            disabled={!batch.is_open}
                            onChange={() => onChange(batch.id)}
                            className="mt-1 accent-primary"
                        />
                        <span className="flex flex-1 flex-col gap-0.5">
                            <span className="font-medium">{batch.name}</span>
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                <CalendarDays className="size-3" />
                                {formatDateRange(
                                    batch.starts_on,
                                    batch.ends_on,
                                )}{' '}
                                · {batch.status_label}
                            </span>
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Users className="size-3" />
                                {batch.is_open
                                    ? batch.seats_left === null
                                        ? 'Kuota tidak terbatas'
                                        : `Sisa ${batch.seats_left} kursi`
                                    : batch.closed_reason}
                            </span>
                        </span>
                    </label>
                );
            })}
        </fieldset>
    );
}
