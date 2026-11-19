import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/admin';
import { edit, update } from '@/routes/admin/certificate-settings';
import { index as certificatesIndex } from '@/routes/admin/certificates';
import type { CertificateSettings } from '@/types/certificates';

const fields: {
    key: keyof CertificateSettings;
    label: string;
    max: number;
    hint?: string;
}[] = [
    { key: 'institution_name', label: 'Nama lembaga', max: 100 },
    { key: 'title', label: 'Judul sertifikat', max: 60 },
    {
        key: 'statement',
        label: 'Kalimat pernyataan',
        max: 120,
        hint: 'Dicetak di antara nama penerima dan judul kursus.',
    },
    { key: 'signer_name', label: 'Nama penanda tangan', max: 80 },
    { key: 'signer_title', label: 'Jabatan penanda tangan', max: 80 },
];

export default function CertificateSettingsPage({
    settings,
}: {
    settings: CertificateSettings;
}) {
    return (
        <>
            <Head title="Pengaturan sertifikat" />
            <div className="flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pengaturan sertifikat
                    </h1>
                    <p className="text-muted-foreground">
                        Teks yang dicetak pada setiap sertifikat PDF.
                    </p>
                </div>

                <Alert>
                    <AlertDescription>
                        Perubahan berlaku untuk PDF yang dibuat setelah
                        disimpan. Gunakan &quot;Buat ulang PDF&quot; di daftar
                        sertifikat untuk memperbarui sertifikat lama.
                    </AlertDescription>
                </Alert>

                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-5 rounded-lg border bg-card p-5"
                >
                    {({ errors, processing }) => (
                        <>
                            {fields.map((field) => (
                                <div key={field.key} className="grid gap-2">
                                    <Label htmlFor={field.key}>
                                        {field.label}
                                    </Label>
                                    <Input
                                        id={field.key}
                                        name={field.key}
                                        defaultValue={settings[field.key]}
                                        maxLength={field.max}
                                        required
                                    />
                                    {field.hint && (
                                        <p className="text-xs text-muted-foreground">
                                            {field.hint}
                                        </p>
                                    )}
                                    <InputError message={errors[field.key]} />
                                </div>
                            ))}
                            <div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Simpan
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CertificateSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Sertifikat', href: certificatesIndex() },
        { title: 'Pengaturan', href: edit() },
    ],
};
