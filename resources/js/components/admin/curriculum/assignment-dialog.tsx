import { router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { RichTextEditor } from '@/components/admin/rich-text-editor';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store, update } from '@/routes/admin/assignments';
import type { AssignmentForm } from '@/types/assignments';

type Option = { value: string; label: string };

/**
 * Create/edit the assignment of a lesson (lessonId) or the course's final
 * project (lessonId null). Instructions are sanitised on the server.
 */
export function AssignmentDialog({
    courseId,
    lessonId,
    lessonTitle,
    assignment,
    statuses,
    maxImages,
    dripEnabled,
    open,
    onOpenChange,
}: {
    courseId: number;
    lessonId: number | null;
    lessonTitle?: string;
    assignment?: AssignmentForm | null;
    statuses: Option[];
    maxImages: number;
    dripEnabled: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        lesson_id: lessonId,
        title: '',
        instructions_html: '',
        due_offset_days: '3',
        max_images: String(maxImages),
        is_required: false,
        status: 'draft',
    });

    useEffect(() => {
        if (open) {
            form.setData({
                lesson_id: lessonId,
                title:
                    assignment?.title ??
                    (lessonId === null ? 'Proyek akhir' : ''),
                instructions_html: assignment?.instructions_html ?? '',
                due_offset_days: String(assignment?.due_offset_days ?? 3),
                max_images: String(assignment?.max_images ?? maxImages),
                is_required: assignment?.is_required ?? false,
                status: assignment?.status ?? 'draft',
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset when (re)opened
    }, [open, assignment?.id, lessonId]);

    const pageErrors = usePage().props.errors as Record<
        string,
        string | undefined
    >;
    const isProject = lessonId === null;
    const offset = Number(form.data.due_offset_days) || 0;
    const help = isProject
        ? dripEnabled
            ? `Tenggat = ${offset} hari setelah batch berakhir, pukul 23:59 WIB.`
            : `Tenggat = ${offset} hari setelah siswa terdaftar, pukul 23:59 WIB.`
        : dripEnabled
          ? `Tenggat = ${offset} hari setelah lesson terbuka, berakhir 23:59 WIB.`
          : `Tenggat = ${offset} hari setelah siswa terdaftar, berakhir 23:59 WIB.`;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(assignment ? update(assignment.id) : store(courseId), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const remove = () => {
        if (
            assignment &&
            window.confirm(
                'Hapus tugas ini? Tugas yang sudah memiliki pengumpulan tidak bisa dihapus.',
            )
        ) {
            router.delete(destroy(assignment.id).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        }
    };

    const statusLabel = statuses.find(
        (status) => status.value === form.data.status,
    )?.label;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <DialogHeader>
                        <DialogTitle>
                            {isProject
                                ? 'Proyek akhir'
                                : assignment
                                  ? 'Ubah tugas'
                                  : 'Tugas baru'}
                        </DialogTitle>
                        <DialogDescription>
                            {isProject
                                ? 'Satu proyek akhir per kursus. Siswa mengumpulkan gambar.'
                                : `Tugas gambar untuk lesson "${lessonTitle ?? ''}".`}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="assignment-title">Judul tugas</Label>
                        <Input
                            id="assignment-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="assignment-instructions">
                            Instruksi
                        </Label>
                        <RichTextEditor
                            id="assignment-instructions"
                            value={form.data.instructions_html}
                            onChange={(html) =>
                                form.setData('instructions_html', html)
                            }
                            invalid={Boolean(form.errors.instructions_html)}
                            placeholder="Jelaskan apa yang harus dikumpulkan…"
                        />
                        <InputError message={form.errors.instructions_html} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="assignment-offset">
                                Tenggat (hari)
                            </Label>
                            <Input
                                id="assignment-offset"
                                type="number"
                                min={0}
                                max={365}
                                inputMode="numeric"
                                value={form.data.due_offset_days}
                                onChange={(event) =>
                                    form.setData(
                                        'due_offset_days',
                                        event.target.value,
                                    )
                                }
                                aria-describedby="assignment-offset-help"
                            />
                            <InputError message={form.errors.due_offset_days} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="assignment-images">
                                Maks. gambar
                            </Label>
                            <Input
                                id="assignment-images"
                                type="number"
                                min={1}
                                max={maxImages}
                                inputMode="numeric"
                                value={form.data.max_images}
                                onChange={(event) =>
                                    form.setData(
                                        'max_images',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.max_images} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="assignment-status">Status</Label>
                            <Select
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData('status', value)
                                }
                            >
                                <SelectTrigger
                                    id="assignment-status"
                                    className="w-full"
                                >
                                    <SelectValue>{statusLabel}</SelectValue>
                                </SelectTrigger>
                                <SelectContent>
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
                            <InputError message={form.errors.status} />
                        </div>
                    </div>
                    <p
                        id="assignment-offset-help"
                        className="-mt-2 text-xs text-muted-foreground"
                    >
                        {help} Tugas draf tidak tampil ke siswa.
                    </p>

                    <InputError message={pageErrors.assignment} />

                    <label className="flex items-start gap-3">
                        <Checkbox
                            checked={form.data.is_required}
                            onCheckedChange={(checked) =>
                                form.setData('is_required', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span>
                            <span className="text-sm font-medium">Wajib</span>
                            <span className="block text-xs text-muted-foreground">
                                Disimpan untuk syarat kelulusan (diterapkan di
                                fase sertifikat).
                            </span>
                        </span>
                    </label>

                    <DialogFooter className="sm:justify-between">
                        {assignment ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive"
                                onClick={remove}
                            >
                                <Trash2 />
                                Hapus tugas
                            </Button>
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
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
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
