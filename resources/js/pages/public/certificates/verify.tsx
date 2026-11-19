import { Form } from '@inertiajs/react';
import { BadgeCheck, CircleX, SearchX, ShieldCheck } from 'lucide-react';
import { SeoHead } from '@/components/seo-head';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import { form as verifyForm } from '@/routes/certificates/verify';
import type { VerificationResult } from '@/types/certificates';

type Props = {
    meta: { title: string; description: string; noindex?: boolean };
    code: string | null;
    result: VerificationResult | null;
};

/**
 * Public verification. Shows only what is printed on the certificate (no
 * email/phone/account data).
 */
export default function VerifyCertificate({ meta, code, result }: Props) {
    return (
        <>
            <SeoHead
                title={meta.title}
                description={meta.description}
                noindex={meta.noindex}
            />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 px-4 py-10 md:px-6 md:py-16">
                <div className="space-y-2 text-center">
                    <ShieldCheck className="mx-auto size-10 text-primary" />
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Verifikasi sertifikat
                    </h1>
                    <p className="text-muted-foreground">
                        Masukkan kode verifikasi yang tercetak di sertifikat
                        untuk memeriksa keasliannya.
                    </p>
                </div>

                <Form
                    action={verifyForm()}
                    className="flex flex-col gap-2 sm:flex-row sm:items-end"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="kode">Kode verifikasi</Label>
                        <Input
                            id="kode"
                            name="kode"
                            defaultValue={code ?? ''}
                            placeholder="Contoh: K7QF2M9XHT4P"
                            autoComplete="off"
                            maxLength={40}
                            required
                            className="font-mono uppercase"
                        />
                    </div>
                    <Button type="submit">Periksa</Button>
                </Form>

                {result && <ResultCard result={result} />}
            </div>
        </>
    );
}

function ResultCard({ result }: { result: VerificationResult }) {
    if (result.status === 'not_found') {
        return (
            <section
                aria-live="polite"
                className="flex items-start gap-3 rounded-lg border bg-card p-5"
            >
                <SearchX className="mt-0.5 size-6 shrink-0 text-muted-foreground" />
                <div className="space-y-1">
                    <h2 className="font-semibold">
                        Sertifikat tidak ditemukan
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Tidak ada sertifikat dengan kode ini. Periksa kembali
                        kode yang Anda masukkan.
                    </p>
                </div>
            </section>
        );
    }

    const valid = result.status === 'valid';

    return (
        <section
            aria-live="polite"
            className="flex flex-col gap-4 rounded-lg border bg-card p-5"
        >
            <div className="flex items-start gap-3">
                {valid ? (
                    <BadgeCheck className="mt-0.5 size-6 shrink-0 text-primary" />
                ) : (
                    <CircleX className="mt-0.5 size-6 shrink-0 text-destructive" />
                )}
                <div className="space-y-1">
                    <h2 className="font-semibold">
                        {valid ? 'Sertifikat valid' : 'Sertifikat dicabut'}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {valid
                            ? 'Sertifikat ini asli dan masih berlaku.'
                            : `Sertifikat ini telah dicabut${result.revoked_at ? ` pada ${formatDate(result.revoked_at)}` : ''} dan tidak lagi berlaku.`}
                    </p>
                </div>
            </div>
            <dl className="grid gap-3 text-sm sm:grid-cols-[10rem_1fr]">
                <dt className="text-muted-foreground">Nama penerima</dt>
                <dd className="font-medium break-words">
                    {result.recipient_name}
                </dd>
                <dt className="text-muted-foreground">Kursus</dt>
                <dd className="break-words">{result.course_title}</dd>
                {result.batch_name && (
                    <>
                        <dt className="text-muted-foreground">Batch</dt>
                        <dd>{result.batch_name}</dd>
                    </>
                )}
                <dt className="text-muted-foreground">Tanggal terbit</dt>
                <dd>{formatDate(result.issued_at)}</dd>
                <dt className="text-muted-foreground">Nomor sertifikat</dt>
                <dd className="font-mono">{result.number}</dd>
            </dl>
        </section>
    );
}
