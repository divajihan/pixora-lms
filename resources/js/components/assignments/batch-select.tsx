import { router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BatchOptionLite } from '@/types/assignments';

/**
 * Batch switcher for reviewer pages (drip courses): reloads with ?batch=ID.
 */
export function BatchSelect({
    batches,
    selectedId,
    url,
}: {
    batches: BatchOptionLite[];
    selectedId: number | null;
    url: string;
}) {
    if (batches.length === 0) {
        return null;
    }

    const selected = batches.find((batch) => batch.id === selectedId);

    return (
        <div className="flex items-center gap-2">
            <label
                htmlFor="batch-select"
                className="text-sm text-muted-foreground"
            >
                Batch
            </label>
            <Select
                value={selectedId ? String(selectedId) : undefined}
                onValueChange={(value) =>
                    router.get(url, { batch: value }, { preserveScroll: true })
                }
            >
                <SelectTrigger id="batch-select" className="w-56">
                    <SelectValue placeholder="Pilih batch">
                        {selected
                            ? `${selected.name} · ${selected.status_label}`
                            : undefined}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    {batches.map((batch) => (
                        <SelectItem key={batch.id} value={String(batch.id)}>
                            {batch.name} · {batch.status_label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
