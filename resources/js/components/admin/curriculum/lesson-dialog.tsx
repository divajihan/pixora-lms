import { useForm } from '@inertiajs/react';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDuration } from '@/lib/format';
import { store, update } from '@/routes/admin/lessons';
import type {
    CurriculumLesson,
    CurriculumModule,
    LessonStatusValue,
    LessonTypeValue,
    Option,
} from '@/types/admin';

type LessonFormData = {
    title: string;
    type: LessonTypeValue;
    status: LessonStatusValue;
    video_url: string;
    summary_html: string;
    drip_day_offset: string;
    is_preview: boolean;
    duration_seconds: string;
    live_starts_at: string;
    live_ends_at: string;
    live_platform: string;
    live_meeting_url: string;
    live_notes: string;
};

function initialData(lesson?: CurriculumLesson): LessonFormData {
    return {
        title: lesson?.title ?? '',
        type: lesson?.type ?? 'video',
        status: lesson?.status ?? 'draft',
        video_url: lesson?.video_url ?? '',
        summary_html: lesson?.summary_html ?? '',
        drip_day_offset: String(lesson?.drip_day_offset ?? 0),
        is_preview: lesson?.is_preview ?? false,
        duration_seconds: lesson?.duration_seconds
            ? String(lesson.duration_seconds)
            : '',
        live_starts_at: lesson?.live?.starts_at ?? '',
        live_ends_at: lesson?.live?.ends_at ?? '',
        live_platform: lesson?.live?.platform ?? '',
        live_meeting_url: lesson?.live?.meeting_url ?? '',
        live_notes: lesson?.live?.notes ?? '',
    };
}

/**
 * Create a lesson in `module` or edit `lesson`.
 */
export function LessonDialog({
    module,
    lesson,
    open,
    onOpenChange,
    types,
    statuses,
    dripEnabled,
}: {
    module: CurriculumModule;
    lesson?: CurriculumLesson;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    types: Option<LessonTypeValue>[];
    statuses: Option<LessonStatusValue>[];
    dripEnabled: boolean;
}) {
    const form = useForm<LessonFormData>(initialData(lesson));
    const { data, setData, errors, processing } = form;

    useEffect(() => {
        if (open) {
            setData(initialData(lesson));
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when (re)opened
    }, [open, lesson?.id]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(lesson ? update(lesson.id) : store(module.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const typeLabel =
        types.find((type) => type.value === data.type)?.label ?? data.type;
    const statusLabel =
        statuses.find((status) => status.value === data.status)?.label ??
        data.status;
    const isLive = data.type === 'live';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="grid gap-5" noValidate>
                    <DialogHeader>
                        <DialogTitle>
                            {lesson ? 'Ubah lesson' : 'Lesson baru'}
                        </DialogTitle>
                        <DialogDescription>
                            Modul: {module.title}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="lesson-title">Judul</Label>
                        <Input
                            id="lesson-title"
                            value={data.title}
                            onChange={(event) =>
                                setData('title', event.target.value)
                            }
                            maxLength={200}
                            autoFocus
                            aria-invalid={Boolean(errors.title)}
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="lesson-type">Tipe</Label>
                            <Select
                                value={data.type}
                                onValueChange={(value) =>
                                    setData('type', value as LessonTypeValue)
                                }
                            >
                                <SelectTrigger
                                    id="lesson-type"
                                    className="w-full"
                                >
                                    <SelectValue>{typeLabel}</SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lesson-status">Status</Label>
                            <Select
                                value={data.status}
                                onValueChange={(value) =>
                                    setData(
                                        'status',
                                        value as LessonStatusValue,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="lesson-status"
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
                            <InputError message={errors.status} />
                        </div>
                    </div>

                    {!isLive && (
                        <div className="grid gap-2">
                            <Label htmlFor="lesson-video">
                                URL video YouTube
                            </Label>
                            <Input
                                id="lesson-video"
                                value={data.video_url}
                                onChange={(event) =>
                                    setData('video_url', event.target.value)
                                }
                                placeholder="https://www.youtube.com/watch?v=… atau https://youtu.be/…"
                                inputMode="url"
                                aria-describedby="lesson-video-help"
                                aria-invalid={Boolean(errors.video_url)}
                            />
                            <p
                                id="lesson-video-help"
                                className="text-xs text-muted-foreground"
                            >
                                Gunakan video YouTube berstatus unlisted. Hanya
                                ID video yang disimpan.
                            </p>
                            <InputError message={errors.video_url} />
                        </div>
                    )}

                    {isLive && (
                        <fieldset className="grid gap-4 rounded-md border p-4">
                            <legend className="px-1 text-sm font-medium">
                                Jadwal kelas live
                            </legend>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="live-start">Mulai</Label>
                                    <Input
                                        id="live-start"
                                        type="datetime-local"
                                        value={data.live_starts_at}
                                        onChange={(event) =>
                                            setData(
                                                'live_starts_at',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.live_starts_at,
                                        )}
                                    />
                                    <InputError
                                        message={errors.live_starts_at}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="live-end">Selesai</Label>
                                    <Input
                                        id="live-end"
                                        type="datetime-local"
                                        value={data.live_ends_at}
                                        min={data.live_starts_at || undefined}
                                        onChange={(event) =>
                                            setData(
                                                'live_ends_at',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.live_ends_at,
                                        )}
                                    />
                                    <InputError message={errors.live_ends_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="live-platform">
                                        Platform
                                    </Label>
                                    <Input
                                        id="live-platform"
                                        value={data.live_platform}
                                        onChange={(event) =>
                                            setData(
                                                'live_platform',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Zoom, Google Meet, …"
                                        maxLength={50}
                                    />
                                    <InputError
                                        message={errors.live_platform}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="live-url">
                                        Tautan meeting
                                    </Label>
                                    <Input
                                        id="live-url"
                                        type="url"
                                        value={data.live_meeting_url}
                                        onChange={(event) =>
                                            setData(
                                                'live_meeting_url',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="https://…"
                                        aria-invalid={Boolean(
                                            errors.live_meeting_url,
                                        )}
                                    />
                                    <InputError
                                        message={errors.live_meeting_url}
                                    />
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Waktu dalam WIB (Asia/Jakarta).
                            </p>
                            <div className="grid gap-2">
                                <Label htmlFor="live-notes">Catatan</Label>
                                <Textarea
                                    id="live-notes"
                                    value={data.live_notes}
                                    onChange={(event) =>
                                        setData(
                                            'live_notes',
                                            event.target.value,
                                        )
                                    }
                                    rows={2}
                                    maxLength={2000}
                                />
                                <InputError message={errors.live_notes} />
                            </div>
                        </fieldset>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="lesson-summary">Ringkasan materi</Label>
                        <RichTextEditor
                            id="lesson-summary"
                            value={data.summary_html}
                            onChange={(html) => setData('summary_html', html)}
                            invalid={Boolean(errors.summary_html)}
                            placeholder="Ringkasan materi"
                        />
                        <InputError message={errors.summary_html} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="lesson-drip">Hari buka</Label>
                            <Input
                                id="lesson-drip"
                                type="number"
                                min={0}
                                inputMode="numeric"
                                value={data.drip_day_offset}
                                onChange={(event) =>
                                    setData(
                                        'drip_day_offset',
                                        event.target.value,
                                    )
                                }
                                aria-describedby="lesson-drip-help"
                                aria-invalid={Boolean(errors.drip_day_offset)}
                            />
                            <p
                                id="lesson-drip-help"
                                className="text-xs text-muted-foreground"
                            >
                                Hari ke-N sejak siswa terdaftar. 0 = langsung
                                terbuka.
                                {!dripEnabled &&
                                    ' Drip belum aktif untuk kursus ini.'}
                            </p>
                            <InputError message={errors.drip_day_offset} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lesson-duration">
                                Durasi (detik)
                            </Label>
                            <Input
                                id="lesson-duration"
                                type="number"
                                min={1}
                                inputMode="numeric"
                                value={data.duration_seconds}
                                onChange={(event) =>
                                    setData(
                                        'duration_seconds',
                                        event.target.value,
                                    )
                                }
                                placeholder="Opsional"
                                aria-describedby="lesson-duration-help"
                                aria-invalid={Boolean(errors.duration_seconds)}
                            />
                            <p
                                id="lesson-duration-help"
                                className="text-xs text-muted-foreground"
                            >
                                {data.duration_seconds
                                    ? `≈ ${formatDuration(Number(data.duration_seconds))}`
                                    : 'Contoh: 600 = 10 menit.'}
                            </p>
                            <InputError message={errors.duration_seconds} />
                        </div>
                    </div>

                    <label className="flex items-start gap-3">
                        <Checkbox
                            checked={data.is_preview}
                            onCheckedChange={(checked) =>
                                setData('is_preview', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span>
                            <span className="text-sm font-medium">
                                Pratinjau gratis
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Lesson terbit dengan pratinjau bisa diputar
                                siapa saja di halaman kursus.
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
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {lesson ? 'Simpan lesson' : 'Tambah lesson'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
