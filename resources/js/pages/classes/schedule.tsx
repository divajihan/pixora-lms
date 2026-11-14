import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Lock, RotateCcw, Save } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateRange, formatUnlock } from '@/lib/format';
import {
    batches,
    schedule as scheduleRoute,
    studentSchedule,
    students,
} from '@/routes/classes';
import {
    reset as resetBatch,
    set as setBatch,
} from '@/routes/classes/schedule';
import {
    reset as resetStudent,
    set as setStudent,
} from '@/routes/classes/student-schedule';
import type { BatchData } from '@/types/learning';

type Row = {
    id: number;
    title: string;
    type: 'video' | 'live';
    is_preview: boolean;
    day_number: number;
    default_unlock_at: string;
    batch_unlock_at: string;
    override_unlock_at: string | null;
    effective_unlock_at: string;
    is_open: boolean;
};

type Props = {
    course: { id: number; title: string; slug: string };
    batch: BatchData;
    student: { id: number; name: string; email: string } | null;
    modules: { id: number; title: string; lessons: Row[] }[];
};

/** "2026-11-16T00:00:00+07:00" → "2026-11-16T00:00" (datetime-local, WIB). */
function toLocalInput(iso: string): string {
    const parts = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));

    return parts.replace(' ', 'T');
}

function ScheduleRow({ row, props }: { row: Row; props: Props }) {
    const { course, batch, student } = props;
    const [value, setValue] = useState(toLocalInput(row.effective_unlock_at));
    const [saving, setSaving] = useState(false);

    const route = student
        ? {
              set: setStudent([course.id, batch.id, student.id, row.id]),
              reset: resetStudent([course.id, batch.id, student.id, row.id]),
          }
        : {
              set: setBatch([course.id, batch.id, row.id]),
              reset: resetBatch([course.id, batch.id, row.id]),
          };

    const options = {
        preserveScroll: true,
        onStart: () => setSaving(true),
        onFinish: () => setSaving(false),
    };

    return (
        <tr className="align-top">
            <td className="px-4 py-3">
                <p className="font-medium">{row.title}</p>
                <p className="text-xs text-muted-foreground">
                    Hari ke-{row.day_number} ·{' '}
                    {row.type === 'live' ? 'Kelas live' : 'Video'}
                    {row.is_preview && ' · Pratinjau (selalu terbuka)'}
                </p>
            </td>
            <td className="px-4 py-3 text-xs text-muted-foreground">
                {formatUnlock(
                    student ? row.batch_unlock_at : row.default_unlock_at,
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1.5 text-xs">
                    {row.is_open ? (
                        <CheckCircle2 className="size-4 text-primary" />
                    ) : (
                        <Lock className="size-4 text-muted-foreground" />
                    )}
                    <span>{formatUnlock(row.effective_unlock_at)}</span>
                </div>
                {row.override_unlock_at && (
                    <Badge variant="secondary" className="mt-1">
                        Jadwal khusus
                    </Badge>
                )}
            </td>
            <td className="px-4 py-3">
                {row.is_open || row.is_preview ? (
                    <p className="text-xs text-muted-foreground">
                        Sudah terbuka — tidak dapat dikunci kembali.
                    </p>
                ) : (
                    <div className="flex flex-wrap items-center gap-1">
                        <Input
                            type="datetime-local"
                            value={value}
                            onChange={(event) => setValue(event.target.value)}
                            className="h-8 w-48"
                            aria-label={`Tanggal buka ${row.title}`}
                        />
                        <Button
                            size="sm"
                            disabled={saving || !value}
                            onClick={() =>
                                router.post(
                                    route.set.url,
                                    { unlock_at: value },
                                    options,
                                )
                            }
                        >
                            {saving ? <Spinner /> : <Save />}
                            Simpan
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                setValue(toLocalInput(new Date().toISOString()))
                            }
                        >
                            Buka sekarang
                        </Button>
                    </div>
                )}
                {row.override_unlock_at && (
                    <Button
                        size="sm"
                        variant="link"
                        className="h-auto px-0"
                        disabled={saving}
                        onClick={() => router.delete(route.reset.url, options)}
                    >
                        <RotateCcw />
                        Kembalikan ke jadwal {student ? 'batch' : 'default'}
                    </Button>
                )}
            </td>
        </tr>
    );
}

export default function ClassSchedule(props: Props) {
    const { course, batch, student, modules } = props;
    const { errors } = usePage().props as { errors: Record<string, string> };

    return (
        <>
            <Head
                title={
                    student ? `Jadwal ${student.name}` : `Jadwal ${batch.name}`
                }
            />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {student
                            ? `Jadwal khusus: ${student.name}`
                            : `Jadwal lesson ${batch.name}`}
                    </h1>
                    <p className="text-muted-foreground">
                        {course.title} ·{' '}
                        {formatDateRange(batch.starts_on, batch.ends_on)}. Waktu
                        dalam WIB.
                    </p>
                </div>

                <Alert>
                    <Lock />
                    <AlertTitle>
                        Lesson yang sudah terbuka tetap terbuka
                    </AlertTitle>
                    <AlertDescription>
                        Anda bisa membuka lesson lebih awal atau menggeser
                        lesson yang belum terbuka ke tanggal lain.
                        {student
                            ? ' Jadwal khusus siswa mengalahkan jadwal batch.'
                            : ' Jadwal khusus per siswa tetap berlaku di atas jadwal batch.'}
                    </AlertDescription>
                </Alert>

                {errors.unlock_at && (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.unlock_at}
                    </p>
                )}

                {modules.map((module) => (
                    <section
                        key={module.id}
                        aria-label={module.title}
                        className="flex flex-col gap-2"
                    >
                        <h2 className="font-semibold">{module.title}</h2>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[820px] text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            Lesson
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            {student
                                                ? 'Jadwal batch'
                                                : 'Jadwal default'}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            Efektif
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            Atur tanggal buka
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {module.lessons.map((row) => (
                                        <ScheduleRow
                                            key={`${row.id}-${row.effective_unlock_at}`}
                                            row={row}
                                            props={props}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

ClassSchedule.layout = (props: Props) => ({
    breadcrumbs: [
        { title: props.course.title, href: batches(props.course.id) },
        {
            title: props.batch.name,
            href: students([props.course.id, props.batch.id]),
        },
        props.student
            ? {
                  title: props.student.name,
                  href: studentSchedule([
                      props.course.id,
                      props.batch.id,
                      props.student.id,
                  ]),
              }
            : {
                  title: 'Jadwal',
                  href: scheduleRoute([props.course.id, props.batch.id]),
              },
    ],
});
