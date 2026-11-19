import { Head, Link, router } from '@inertiajs/react';
import {
    Award,
    Download,
    ExternalLink,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Search,
    Settings,
    X,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import {
    IssueCertificateDialog,
    RevokeCertificateDialog,
} from '@/components/certificates/admin-certificate-dialogs';
import { Pagination } from '@/components/catalog/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { edit as settingsRoute } from '@/routes/admin/certificate-settings';
import {
    index as certificatesIndex,
    regenerate,
} from '@/routes/admin/certificates';
import type { Paginated } from '@/types/catalog';
import type { AdminCertificate } from '@/types/certificates';

const ALL = 'semua';

type Filters = {
    q: string | null;
    status: 'aktif' | 'dicabut' | null;
    kursus: number | null;
    batch: number | null;
};

export default function AdminCertificates({
    certificates,
    filters,
    courses,
    batches,
    can,
}: {
    certificates: Paginated<AdminCertificate>;
    filters: Filters;
    courses: { id: number; title: string }[];
    batches: { id: number; name: string }[];
    can: { manage: boolean; settings: boolean };
}) {
    const [search, setSearch] = useState(filters.q ?? '');
    const [loading, setLoading] = useState(false);
    const [issueOpen, setIssueOpen] = useState(false);
    const [revoking, setRevoking] = useState<AdminCertificate | null>(null);

    const apply = (next: Partial<Filters>) => {
        const query = Object.fromEntries(
            Object.entries({
                ...filters,
                q: search.trim() || null,
                ...next,
            }).filter(([, value]) => value),
        );

        router.get(certificatesIndex().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    const hasFilters = Boolean(
        filters.q || filters.status || filters.kursus || filters.batch,
    );
    const courseLabel =
        courses.find((course) => course.id === filters.kursus)?.title ??
        'Semua kursus';
    const batchLabel =
        batches.find((batch) => batch.id === filters.batch)?.name ??
        'Semua batch';

    return (
        <>
            <Head title="Sertifikat" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Sertifikat
                        </h1>
                        <p className="text-muted-foreground">
                            Sertifikat yang telah diterbitkan untuk siswa.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.settings && (
                            <Button asChild variant="outline">
                                <Link href={settingsRoute()}>
                                    <Settings />
                                    Pengaturan
                                </Link>
                            </Button>
                        )}
                        {can.manage && (
                            <Button onClick={() => setIssueOpen(true)}>
                                <Plus />
                                Terbitkan manual
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-col gap-2 lg:flex-row">
                    <form
                        role="search"
                        onSubmit={submit}
                        className="flex flex-1 gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nomor, nama, atau kursus…"
                                aria-label="Cari nomor, nama, atau kursus"
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            Cari
                        </Button>
                    </form>
                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) =>
                            apply({
                                status:
                                    value === ALL
                                        ? null
                                        : (value as Filters['status']),
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label="Filter status"
                            className="w-full lg:w-40"
                        >
                            <SelectValue>
                                {filters.status === 'aktif'
                                    ? 'Aktif'
                                    : filters.status === 'dicabut'
                                      ? 'Dicabut'
                                      : 'Semua status'}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua status</SelectItem>
                            <SelectItem value="aktif">Aktif</SelectItem>
                            <SelectItem value="dicabut">Dicabut</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.kursus ? String(filters.kursus) : ALL}
                        onValueChange={(value) =>
                            apply({
                                kursus: value === ALL ? null : Number(value),
                                batch: null,
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label="Filter kursus"
                            className="w-full lg:w-56"
                        >
                            <SelectValue>{courseLabel}</SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua kursus</SelectItem>
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
                    {batches.length > 0 && (
                        <Select
                            value={filters.batch ? String(filters.batch) : ALL}
                            onValueChange={(value) =>
                                apply({
                                    batch: value === ALL ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger
                                aria-label="Filter batch"
                                className="w-full lg:w-44"
                            >
                                <SelectValue>{batchLabel}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Semua batch</SelectItem>
                                {batches.map((batch) => (
                                    <SelectItem
                                        key={batch.id}
                                        value={String(batch.id)}
                                    >
                                        {batch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span aria-live="polite">
                        {loading
                            ? 'Memuat…'
                            : `${certificates.total} sertifikat`}
                    </span>
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                router.get(certificatesIndex().url);
                            }}
                        >
                            <X />
                            Hapus filter
                        </Button>
                    )}
                </div>

                {certificates.data.length === 0 ? (
                    <EmptyState
                        icon={Award}
                        title={
                            hasFilters
                                ? 'Tidak ada sertifikat yang cocok'
                                : 'Belum ada sertifikat'
                        }
                    />
                ) : (
                    <div
                        className={`overflow-x-auto rounded-lg border transition-opacity ${loading ? 'opacity-60' : ''}`}
                    >
                        <table className="w-full min-w-[820px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Sertifikat
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Penerima
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Kursus
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {certificates.data.map((certificate) => (
                                    <CertificateRow
                                        key={certificate.id}
                                        certificate={certificate}
                                        canManage={can.manage}
                                        onRevoke={() =>
                                            setRevoking(certificate)
                                        }
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={certificates.links} />
            </div>

            {can.manage && (
                <>
                    <IssueCertificateDialog
                        open={issueOpen}
                        onOpenChange={setIssueOpen}
                        courses={courses}
                    />
                    <RevokeCertificateDialog
                        certificate={revoking}
                        onOpenChange={(open) => !open && setRevoking(null)}
                    />
                </>
            )}
        </>
    );
}

function CertificateRow({
    certificate,
    canManage,
    onRevoke,
}: {
    certificate: AdminCertificate;
    canManage: boolean;
    onRevoke: () => void;
}) {
    const revoked = certificate.status === 'revoked';

    return (
        <tr className="align-top">
            <td className="px-4 py-3">
                <p className="font-mono font-medium">{certificate.number}</p>
                <p className="text-xs text-muted-foreground">
                    Terbit {formatDate(certificate.issued_at)}
                    {certificate.is_manual &&
                        ` · manual oleh ${certificate.issued_by ?? 'admin'}`}
                </p>
                {certificate.is_manual && certificate.issue_reason && (
                    <p className="text-xs text-muted-foreground">
                        Alasan: {certificate.issue_reason}
                    </p>
                )}
            </td>
            <td className="max-w-56 px-4 py-3">
                <p className="break-words">{certificate.recipient_name}</p>
                {certificate.student && (
                    <p className="text-xs break-all text-muted-foreground">
                        {certificate.student.email}
                    </p>
                )}
            </td>
            <td className="max-w-64 px-4 py-3">
                <p className="break-words">{certificate.course_title}</p>
                {certificate.batch_name && (
                    <p className="text-xs text-muted-foreground">
                        {certificate.batch_name}
                    </p>
                )}
            </td>
            <td className="px-4 py-3">
                {revoked ? (
                    <div className="space-y-1">
                        <Badge variant="destructive">Dicabut</Badge>
                        <p className="text-xs text-muted-foreground">
                            {certificate.revoked_at &&
                                formatDate(certificate.revoked_at)}
                            {certificate.revoked_by &&
                                ` · ${certificate.revoked_by}`}
                        </p>
                        {certificate.revoke_reason && (
                            <p className="max-w-48 text-xs break-words text-muted-foreground">
                                {certificate.revoke_reason}
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="space-y-1">
                        <Badge variant="secondary">Aktif</Badge>
                        {!certificate.pdf_ready && (
                            <p className="text-xs text-muted-foreground">
                                PDF diproses
                            </p>
                        )}
                    </div>
                )}
            </td>
            <td className="px-4 py-3 text-right">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Aksi ${certificate.number}`}
                        >
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            asChild
                            disabled={!certificate.pdf_ready}
                        >
                            <a href={certificate.download_url}>
                                <Download />
                                Unduh PDF
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <a
                                href={certificate.verification_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink />
                                Halaman verifikasi
                            </a>
                        </DropdownMenuItem>
                        {canManage && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(
                                            regenerate(certificate.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <RefreshCw />
                                    Buat ulang PDF
                                </DropdownMenuItem>
                                {!revoked && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={onRevoke}
                                    >
                                        <XCircle />
                                        Cabut
                                    </DropdownMenuItem>
                                )}
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </td>
        </tr>
    );
}

AdminCertificates.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Sertifikat', href: certificatesIndex() },
    ],
};
