import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowRightLeft, CalendarClock, Search, Users, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { Pagination } from '@/components/catalog/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateRange } from '@/lib/format';
import {
    batches,
    move,
    schedule,
    studentSchedule,
    students as studentsRoute,
} from '@/routes/classes';
import type { Paginated } from '@/types/catalog';
import type { BatchData } from '@/types/learning';

type Student = {
    id: number;
    name: string;
    email: string;
    status: string;
    status_label: string;
    progress_percent: number;
    enrolled_at: string;
};

type Props = {
    course: { id: number; title: string; slug: string };
    batch: BatchData;
    students: Paginated<Student>;
    filters: { q: string | null };
    /** Admin only: other batches to move a student to. */
    otherBatches: { id: number; name: string }[];
};

function MoveDialog({
    props,
    student,
    onClose,
}: {
    props: Props;
    student: Student | null;
    onClose: () => void;
}) {
    const form = useForm({ batch_id: '' });

    if (!student) {
        return null;
    }

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(move([props.course.id, props.batch.id, student.id]), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const label =
        props.otherBatches.find(
            (batch) => String(batch.id) === form.data.batch_id,
        )?.name ?? 'Pilih batch tujuan';

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Pindahkan {student.name}</DialogTitle>
                        <DialogDescription>
                            Override jadwal khusus siswa ini di batch lama
                            dihapus. Lesson yang sudah terbuka tetap terbuka.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="move-batch">Batch tujuan</Label>
                        <Select
                            value={form.data.batch_id}
                            onValueChange={(value) =>
                                form.setData('batch_id', value)
                            }
                        >
                            <SelectTrigger id="move-batch" className="w-full">
                                <SelectValue>{label}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                {props.otherBatches.map((batch) => (
                                    <SelectItem
                                        key={batch.id}
                                        value={String(batch.id)}
                                    >
                                        {batch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.batch_id} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={!form.data.batch_id || form.processing}
                        >
                            {form.processing && <Spinner />}
                            Pindahkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ClassStudents(props: Props) {
    const { course, batch, students, filters, otherBatches } = props;
    const [search, setSearch] = useState(filters.q ?? '');
    const [moving, setMoving] = useState<Student | null>(null);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            studentsRoute([course.id, batch.id]).url,
            search.trim() ? { q: search.trim() } : {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={`${batch.name} — ${course.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {batch.name}
                        </h1>
                        <p className="text-muted-foreground">
                            {course.title} ·{' '}
                            {formatDateRange(batch.starts_on, batch.ends_on)} ·{' '}
                            {batch.status_label}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={schedule([course.id, batch.id])}>
                            <CalendarClock />
                            Jadwal lesson batch
                        </Link>
                    </Button>
                </div>

                <form role="search" onSubmit={submit} className="flex gap-2">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama atau email siswa…"
                            aria-label="Cari siswa"
                            className="pl-9"
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Cari
                    </Button>
                    {filters.q && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    studentsRoute([course.id, batch.id]).url,
                                );
                            }}
                        >
                            <X />
                            Hapus
                        </Button>
                    )}
                </form>

                <p className="text-sm text-muted-foreground">
                    {students.total} siswa
                    {batch.capacity ? ` · kuota ${batch.capacity}` : ''}
                    {batch.over_capacity && (
                        <span className="text-destructive">
                            {' '}
                            · melebihi kuota
                        </span>
                    )}
                </p>

                {students.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={
                            filters.q
                                ? 'Siswa tidak ditemukan'
                                : 'Belum ada siswa di batch ini'
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Siswa
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Progres
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
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
                                {students.data.map((student) => (
                                    <tr key={student.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {student.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {student.email} · daftar{' '}
                                                {formatDate(
                                                    student.enrolled_at,
                                                )}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className="h-1.5 w-24 overflow-hidden rounded-full bg-muted"
                                                    aria-hidden
                                                >
                                                    <div
                                                        className="h-full rounded-full bg-primary"
                                                        style={{
                                                            width: `${student.progress_percent}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span>
                                                    {student.progress_percent}%
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    student.status === 'active'
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {student.status_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={studentSchedule([
                                                            course.id,
                                                            batch.id,
                                                            student.id,
                                                        ])}
                                                    >
                                                        <CalendarClock />
                                                        Jadwal khusus
                                                    </Link>
                                                </Button>
                                                {otherBatches.length > 0 && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setMoving(student)
                                                        }
                                                    >
                                                        <ArrowRightLeft />
                                                        Pindah batch
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={students.links} />
            </div>

            <MoveDialog
                props={props}
                student={moving}
                onClose={() => setMoving(null)}
            />
        </>
    );
}

ClassStudents.layout = (props: Props) => ({
    breadcrumbs: [
        { title: props.course.title, href: batches(props.course.id) },
        {
            title: props.batch.name,
            href: studentsRoute([props.course.id, props.batch.id]),
        },
    ],
});
