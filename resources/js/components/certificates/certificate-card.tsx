import { router } from '@inertiajs/react';
import {
    Award,
    CheckCircle2,
    Circle,
    Download,
    ExternalLink,
    Loader2,
} from 'lucide-react';
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
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { claim } from '@/routes/learn/certificate';
import type { CertificateCard as CardData } from '@/types/certificates';

const POLL_MS = 4000;
const MAX_POLLS = 45;

/**
 * Requirements checklist + "Klaim sertifikat" (enabled only when the server
 * says eligible; the claim re-checks everything). Once issued: number, PDF
 * download (polls lightly while the PDF is being generated) and verification link.
 */
export function CertificateCard({
    courseSlug,
    data,
}: {
    courseSlug: string;
    data: CardData;
}) {
    const [open, setOpen] = useState(false);
    const certificate = data.certificate;
    const pending = certificate !== null && !certificate.pdf_ready;

    useEffect(() => {
        if (!pending) {
            return;
        }

        let polls = 0;
        const timer = window.setInterval(() => {
            polls += 1;

            if (polls > MAX_POLLS) {
                window.clearInterval(timer);

                return;
            }

            router.reload({ only: ['certificate'] });
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [pending]);

    return (
        <section
            aria-label="Sertifikat"
            className="flex flex-col gap-4 rounded-lg border bg-card p-5"
        >
            <div className="flex items-start gap-3">
                <Award className="mt-0.5 size-5 text-primary" />
                <div className="space-y-1">
                    <h2 className="font-semibold">Sertifikat</h2>
                    <p className="text-sm text-muted-foreground">
                        {certificate
                            ? `Diterbitkan ${formatDate(certificate.issued_at)} atas nama ${certificate.recipient_name}.`
                            : 'Penuhi semua syarat berikut untuk mengklaim sertifikat kursus ini.'}
                    </p>
                </div>
            </div>

            {certificate ? (
                <div className="flex flex-col gap-3 rounded-md bg-muted/50 p-4">
                    <div className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">
                            Nomor sertifikat
                        </span>
                        <span className="font-mono font-medium">
                            {certificate.number}
                        </span>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {certificate.pdf_ready ? (
                            <Button asChild>
                                <a href={certificate.download_url}>
                                    <Download />
                                    Unduh PDF
                                </a>
                            </Button>
                        ) : (
                            <Button disabled>
                                <Loader2 className="animate-spin" />
                                PDF sedang diproses…
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <a
                                href={certificate.verification_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink />
                                Halaman verifikasi
                            </a>
                        </Button>
                    </div>
                </div>
            ) : (
                <>
                    {data.blocker ? (
                        <Alert>
                            <AlertDescription>{data.blocker}</AlertDescription>
                        </Alert>
                    ) : (
                        <ul
                            className="grid gap-2"
                            aria-label="Syarat sertifikat"
                        >
                            {data.requirements.map((requirement) => (
                                <li
                                    key={requirement.key}
                                    className="flex items-start gap-2 text-sm"
                                >
                                    {requirement.met ? (
                                        <CheckCircle2
                                            className="mt-0.5 size-4 shrink-0 text-primary"
                                            aria-label="Terpenuhi"
                                        />
                                    ) : (
                                        <Circle
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-label="Belum terpenuhi"
                                        />
                                    )}
                                    <span
                                        className={
                                            requirement.met
                                                ? ''
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {requirement.label}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div>
                        <Button
                            disabled={!data.eligible}
                            onClick={() => setOpen(true)}
                        >
                            <Award />
                            Klaim sertifikat
                        </Button>
                    </div>
                    <ClaimDialog
                        open={open}
                        onOpenChange={setOpen}
                        courseSlug={courseSlug}
                        defaultName={data.default_name}
                    />
                </>
            )}
        </section>
    );
}

function ClaimDialog({
    open,
    onOpenChange,
    courseSlug,
    defaultName,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    courseSlug: string;
    defaultName: string;
}) {
    const [name, setName] = useState(defaultName);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setName(defaultName);
            setErrors({});
        }
    }, [open, defaultName]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(
            claim(courseSlug).url,
            { recipient_name: name },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => setErrors(errs),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Klaim sertifikat</DialogTitle>
                        <DialogDescription>
                            Periksa nama yang akan dicetak pada sertifikat.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="recipient-name">Nama penerima</Label>
                        <Input
                            id="recipient-name"
                            value={name}
                            maxLength={120}
                            required
                            autoFocus
                            onChange={(event) => setName(event.target.value)}
                        />
                        <InputError message={errors.recipient_name} />
                        <InputError message={errors.certificate} />
                    </div>
                    <Alert>
                        <AlertDescription>
                            Nama tidak dapat diubah setelah sertifikat
                            diterbitkan. Pastikan ejaan dan gelar sudah benar.
                        </AlertDescription>
                    </Alert>
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
                            disabled={processing || name.trim() === ''}
                        >
                            {processing && <Spinner />}
                            Terbitkan sertifikat
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
