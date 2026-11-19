import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Textarea } from '@/components/ui/textarea';
import { revoke, store } from '@/routes/admin/certificates';
import type { AdminCertificate } from '@/types/certificates';

type Errors = Record<string, string>;

/**
 * "Terbitkan manual" (admin exception): bypasses the lesson/assignment/date
 * requirements; reason required and recorded.
 */
export function IssueCertificateDialog({
    open,
    onOpenChange,
    courses,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    courses: { id: number; title: string }[];
}) {
    const empty = { course_id: '', email: '', recipient_name: '', reason: '' };
    const [data, setData] = useState(empty);
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setData({
                course_id: '',
                email: '',
                recipient_name: '',
                reason: '',
            });
            setErrors({});
        }
    }, [open]);

    const set = (key: keyof typeof empty, value: string) =>
        setData((current) => ({ ...current, [key]: value }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(store().url, data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
            onError: (errs) => setErrors(errs),
        });
    };

    const courseTitle = courses.find(
        (course) => String(course.id) === data.course_id,
    )?.title;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Terbitkan sertifikat manual</DialogTitle>
                        <DialogDescription>
                            Untuk pengecualian: syarat lesson, tugas, dan
                            tanggal tidak diperiksa. Siswa harus terdaftar di
                            kursus.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="issue-course">Kursus</Label>
                        <Select
                            value={data.course_id}
                            onValueChange={(value) => set('course_id', value)}
                        >
                            <SelectTrigger id="issue-course">
                                <SelectValue placeholder="Pilih kursus">
                                    {courseTitle}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                {courses.map((course) => (
                                    <SelectItem
                                        key={course.id}
                                        value={String(course.id)}
                                    >
                                        {course.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.course_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="issue-email">Email siswa</Label>
                        <Input
                            id="issue-email"
                            type="email"
                            value={data.email}
                            onChange={(event) =>
                                set('email', event.target.value)
                            }
                            required
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="issue-name">Nama penerima</Label>
                        <Input
                            id="issue-name"
                            value={data.recipient_name}
                            maxLength={120}
                            onChange={(event) =>
                                set('recipient_name', event.target.value)
                            }
                            required
                        />
                        <InputError message={errors.recipient_name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="issue-reason">Alasan</Label>
                        <Textarea
                            id="issue-reason"
                            value={data.reason}
                            maxLength={500}
                            rows={3}
                            onChange={(event) =>
                                set('reason', event.target.value)
                            }
                            required
                        />
                        <InputError message={errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Terbitkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Revoke with a required reason. The verification page then shows "Dicabut"
 * and the student can claim again (new number).
 */
export function RevokeCertificateDialog({
    certificate,
    onOpenChange,
}: {
    certificate: AdminCertificate | null;
    onOpenChange: (open: boolean) => void;
}) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (certificate) {
            setReason('');
            setError(undefined);
        }
    }, [certificate]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!certificate) {
            return;
        }

        router.post(
            revoke(certificate.id).url,
            { reason },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs: Errors) =>
                    setError(errs.reason ?? Object.values(errs)[0]),
            },
        );
    };

    return (
        <Dialog open={certificate !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Cabut sertifikat</DialogTitle>
                        <DialogDescription>
                            {certificate?.number} atas nama{' '}
                            {certificate?.recipient_name}.
                        </DialogDescription>
                    </DialogHeader>
                    <Alert>
                        <AlertDescription>
                            Halaman verifikasi akan menampilkan status
                            &quot;Dicabut&quot;. Siswa dapat mengklaim ulang dan
                            mendapat sertifikat baru dengan nomor baru.
                        </AlertDescription>
                    </Alert>
                    <div className="grid gap-2">
                        <Label htmlFor="revoke-reason">Alasan pencabutan</Label>
                        <Textarea
                            id="revoke-reason"
                            value={reason}
                            maxLength={500}
                            rows={3}
                            onChange={(event) => setReason(event.target.value)}
                            required
                        />
                        <InputError message={error} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing || reason.trim() === ''}
                        >
                            {processing && <Spinner />}
                            Cabut sertifikat
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
