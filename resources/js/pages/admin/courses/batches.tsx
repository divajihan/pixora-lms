import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    Eye,
    EyeOff,
    Pencil,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { CourseTabs } from '@/components/admin/course-tabs';
import { EmptyState } from '@/components/admin/empty-state';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateRange } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import {
    destroy,
    index as batchesIndex,
    publish,
    store,
    update,
} from '@/routes/admin/batches';
import { edit, index as coursesIndex } from '@/routes/admin/courses';
import { students } from '@/routes/classes';
import type { BatchData } from '@/types/learning';

type Props = {
    course: {
        id: number;
        title: string;
        slug: string;
        status: string;
        drip_enabled: boolean;
    };
    batches: BatchData[];
};

function BatchDialog({
    courseId,
    batch,
    open,
    onOpenChange,
}: {
    courseId: number;
    batch?: BatchData;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        name: '',
        starts_on: '',
        ends_on: '',
        registration_closes_on: '',
        capacity: '',
        is_published: true,
    });

    useEffect(() => {
        if (open) {
            form.setData({
                name: batch?.name ?? '',
                starts_on: batch?.starts_on ?? '',
                ends_on: batch?.ends_on ?? '',
                registration_closes_on: batch?.registration_closes_on ?? '',
                capacity: batch?.capacity ? String(batch.capacity) : '',
                is_published: batch?.is_published ?? true,
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset when (re)opened
    }, [open, batch?.id]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(batch ? update(batch.id) : store(courseId), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const started = batch && batch.status !== 'upcoming';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <DialogHeader>
                        <DialogTitle>
                            {batch ? 'Ubah batch' : 'Batch baru'}
                        </DialogTitle>
                        <DialogDescription>
                            Hari ke-1 lesson dihitung dari tanggal mulai batch.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="batch-name">Nama batch</Label>
                        <Input
                            id="batch-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Contoh: Batch 44"
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="batch-start">Tanggal mulai</Label>
                            <Input
                                id="batch-start"
                                type="date"
                                value={form.data.starts_on}
                                onChange={(e) =>
                                    form.setData('starts_on', e.target.value)
                                }
                                disabled={Boolean(started)}
                            />
                            {started && (
                                <p className="text-xs text-muted-foreground">
                                    Tidak bisa diubah: batch sudah dimulai.
                                </p>
                            )}
                            <InputError message={form.errors.starts_on} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="batch-end">Tanggal selesai</Label>
                            <Input
                                id="batch-end"
                                type="date"
                                min={form.data.starts_on || undefined}
                                value={form.data.ends_on}
                                onChange={(e) =>
                                    form.setData('ends_on', e.target.value)
                                }
                            />
                            <InputError message={form.errors.ends_on} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="batch-close">
                                Tutup pendaftaran (opsional)
                            </Label>
                            <Input
                                id="batch-close"
                                type="date"
                                max={form.data.ends_on || undefined}
                                value={form.data.registration_closes_on}
                                onChange={(e) =>
                                    form.setData(
                                        'registration_closes_on',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.registration_closes_on}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="batch-capacity">
                                Kuota (opsional)
                            </Label>
                            <Input
                                id="batch-capacity"
                                type="number"
                                min={1}
                                inputMode="numeric"
                                value={form.data.capacity}
                                onChange={(e) =>
                                    form.setData('capacity', e.target.value)
                                }
                                placeholder="Tidak terbatas"
                            />
                            <InputError message={form.errors.capacity} />
                        </div>
                    </div>
                    <label className="flex items-start gap-3">
                        <Checkbox
                            checked={form.data.is_published}
                            onCheckedChange={(checked) =>
                                form.setData('is_published', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span>
                            <span className="text-sm font-medium">
                                Publikasikan
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Batch terbit bisa dipilih siswa saat membeli.
                            </span>
                        </span>
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminBatches({ course, batches }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [dialog, setDialog] = useState<{ open: boolean; batch?: BatchData }>({
        open: false,
    });
    const [deleting, setDeleting] = useState<BatchData | null>(null);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <Head title={`Batch: ${course.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <h1 className="truncate text-2xl font-semibold tracking-tight">
                            {course.title}
                        </h1>
                        <p className="text-muted-foreground">
                            Batch (angkatan) menentukan jadwal pembukaan lesson.
                        </p>
                    </div>
                    {course.drip_enabled && (
                        <Button onClick={() => setDialog({ open: true })}>
                            <Plus />
                            Batch baru
                        </Button>
                    )}
                </div>

                <CourseTabs
                    courseId={course.id}
                    active="batches"
                    dripEnabled={course.drip_enabled}
                />

                {errors.batch && (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.batch}
                    </p>
                )}

                {!course.drip_enabled ? (
                    <EmptyState
                        icon={CalendarRange}
                        title="Kursus ini tidak memakai batch"
                        description='Aktifkan "Buka lesson bertahap (drip)" di Info kursus untuk menjalankan kelas per batch.'
                    />
                ) : batches.length === 0 ? (
                    <EmptyState
                        icon={CalendarRange}
                        title="Belum ada batch"
                        description="Kursus drip membutuhkan minimal 1 batch terbit sebelum dapat diterbitkan."
                        action={
                            <Button
                                size="sm"
                                onClick={() => setDialog({ open: true })}
                            >
                                <Plus />
                                Batch pertama
                            </Button>
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Batch
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Siswa / kuota
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium"
                                    >
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {batches.map((batch) => (
                                    <tr key={batch.id} className="align-top">
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {batch.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDateRange(
                                                    batch.starts_on,
                                                    batch.ends_on,
                                                )}
                                            </p>
                                            {batch.registration_closes_on && (
                                                <p className="text-xs text-muted-foreground">
                                                    Pendaftaran tutup{' '}
                                                    {formatDate(
                                                        batch.registration_closes_on,
                                                    )}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                <Badge
                                                    variant={
                                                        batch.status ===
                                                        'running'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {batch.status_label}
                                                </Badge>
                                                {!batch.is_published && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-dashed"
                                                    >
                                                        Tidak terbit
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {batch.seats_taken} /{' '}
                                            {batch.capacity ?? '∞'}
                                            {batch.over_capacity && (
                                                <Badge
                                                    variant="outline"
                                                    className="ml-2 text-destructive"
                                                >
                                                    Melebihi kuota
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={students([
                                                            course.id,
                                                            batch.id,
                                                        ])}
                                                    >
                                                        <Users />
                                                        Kelas
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.patch(
                                                            publish(batch.id)
                                                                .url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {batch.is_published ? (
                                                        <EyeOff />
                                                    ) : (
                                                        <Eye />
                                                    )}
                                                    {batch.is_published
                                                        ? 'Nonaktifkan'
                                                        : 'Terbitkan'}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Ubah ${batch.name}`}
                                                    onClick={() =>
                                                        setDialog({
                                                            open: true,
                                                            batch,
                                                        })
                                                    }
                                                >
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Hapus ${batch.name}`}
                                                    onClick={() =>
                                                        setDeleting(batch)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <BatchDialog
                courseId={course.id}
                batch={dialog.batch}
                open={dialog.open}
                onOpenChange={(open) =>
                    setDialog((current) => ({ ...current, open }))
                }
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Hapus batch?"
                description={
                    deleting && deleting.seats_taken > 0
                        ? 'Batch yang sudah memiliki siswa tidak dapat dihapus. Nonaktifkan publikasinya saja.'
                        : `"${deleting?.name ?? ''}" akan dihapus (soft delete).`
                }
                processing={processing}
                onConfirm={() =>
                    deleting &&
                    router.delete(destroy(deleting.id).url, {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setDeleting(null);
                        },
                    })
                }
            />
        </>
    );
}

AdminBatches.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus', href: coursesIndex() },
        { title: props.course.title, href: edit(props.course.id) },
        { title: 'Batch', href: batchesIndex(props.course.id) },
    ],
});
