import { Head, Link } from '@inertiajs/react';
import { Award, Download, ExternalLink, Loader2 } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { course as courseRoute } from '@/routes/learn';
import { dashboard } from '@/routes/student';
import { index } from '@/routes/student/certificates';
import type { OwnedCertificate } from '@/types/certificates';

type Row = OwnedCertificate & { course_slug: string | null };

export default function StudentCertificates({
    certificates,
}: {
    certificates: Row[];
}) {
    return (
        <>
            <Head title="Sertifikat saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Sertifikat saya
                    </h1>
                    <p className="text-muted-foreground">
                        Sertifikat aktif dari kursus yang sudah Anda selesaikan.
                    </p>
                </div>

                {certificates.length === 0 ? (
                    <EmptyState
                        icon={Award}
                        title="Belum ada sertifikat"
                        description="Selesaikan kursus lalu klaim sertifikat dari halaman belajar kursus tersebut."
                    />
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2">
                        {certificates.map((certificate) => (
                            <li
                                key={certificate.id}
                                className="flex flex-col gap-3 rounded-lg border bg-card p-5"
                            >
                                <div className="flex items-start gap-3">
                                    <Award className="mt-0.5 size-5 shrink-0 text-primary" />
                                    <div className="min-w-0 space-y-1">
                                        {certificate.course_slug ? (
                                            <Link
                                                href={courseRoute(
                                                    certificate.course_slug,
                                                )}
                                                className="font-semibold hover:underline"
                                            >
                                                {certificate.course_title}
                                            </Link>
                                        ) : (
                                            <p className="font-semibold">
                                                {certificate.course_title}
                                            </p>
                                        )}
                                        <p className="text-sm break-words text-muted-foreground">
                                            {certificate.recipient_name}
                                            {certificate.batch_name &&
                                                ` · ${certificate.batch_name}`}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            <span className="font-mono">
                                                {certificate.number}
                                            </span>{' '}
                                            · Terbit{' '}
                                            {formatDate(certificate.issued_at)}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {certificate.pdf_ready ? (
                                        <Button asChild size="sm">
                                            <a href={certificate.download_url}>
                                                <Download />
                                                Unduh PDF
                                            </a>
                                        </Button>
                                    ) : (
                                        <Button size="sm" disabled>
                                            <Loader2 className="animate-spin" />
                                            PDF sedang diproses…
                                        </Button>
                                    )}
                                    <Button asChild size="sm" variant="outline">
                                        <a
                                            href={certificate.verification_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink />
                                            Verifikasi
                                        </a>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

StudentCertificates.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Sertifikat saya', href: index() },
    ],
};
