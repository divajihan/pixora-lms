import { Head, router, usePage } from '@inertiajs/react';
import { Copy, KeyRound, Search, UserPlus, Users, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import {
    CreateUserDialog,
    EditUserDialog,
} from '@/components/admin/user-dialogs';
import { Pagination } from '@/components/catalog/pagination';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { dashboard } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminUser, Option } from '@/types/admin';
import type { Paginated } from '@/types/catalog';

type TemporaryPassword = { name: string; email: string; password: string };

const ALL = 'semua';

export default function AdminUsers({
    users,
    filters,
    roles,
}: {
    users: Paginated<AdminUser>;
    filters: { q: string | null; peran: string | null };
    roles: Option[];
}) {
    const page = usePage();
    const temporary = (page.flash as { temporaryPassword?: TemporaryPassword })
        .temporaryPassword;
    const [search, setSearch] = useState(filters.q ?? '');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<AdminUser | null>(null);
    const [loading, setLoading] = useState(false);
    const [copiedText, copy] = useClipboard();

    const apply = (next: Partial<typeof filters>) => {
        const query = Object.fromEntries(
            Object.entries({
                ...filters,
                q: search.trim() || null,
                ...next,
            }).filter(([, value]) => value),
        );

        router.get(usersIndex().url, query, {
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

    const roleLabel = (value: string | null) =>
        roles.find((role) => role.value === value)?.label ?? 'Tanpa peran';
    const hasFilters = Boolean(filters.q || filters.peran);

    return (
        <>
            <Head title="Pengguna" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Pengguna
                        </h1>
                        <p className="text-muted-foreground">
                            Kelola akun, peran, dan profil instruktur.
                        </p>
                    </div>
                    <Button onClick={() => setCreating(true)}>
                        <UserPlus />
                        Akun instruktur/staf
                    </Button>
                </div>

                {temporary && (
                    <Alert>
                        <KeyRound />
                        <AlertTitle>Akun {temporary.name} dibuat</AlertTitle>
                        <AlertDescription>
                            <p>
                                Kata sandi sementara untuk{' '}
                                <strong>{temporary.email}</strong> hanya
                                ditampilkan sekali. Sampaikan secara aman dan
                                minta pengguna menggantinya di Pengaturan →
                                Keamanan.
                            </p>
                            <div className="mt-2 flex items-center gap-2">
                                <code className="rounded bg-muted px-2 py-1 font-mono text-sm text-foreground">
                                    {temporary.password}
                                </code>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => copy(temporary.password)}
                                >
                                    <Copy />
                                    {copiedText === temporary.password
                                        ? 'Tersalin'
                                        : 'Salin'}
                                </Button>
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-2 sm:flex-row">
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
                                placeholder="Cari nama atau email…"
                                aria-label="Cari nama atau email"
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            Cari
                        </Button>
                    </form>
                    <Select
                        value={filters.peran ?? ALL}
                        onValueChange={(value) =>
                            apply({ peran: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger
                            aria-label="Filter peran"
                            className="w-full sm:w-44"
                        >
                            <SelectValue>
                                {filters.peran
                                    ? roleLabel(filters.peran)
                                    : 'Semua peran'}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua peran</SelectItem>
                            {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span aria-live="polite">
                        {loading ? 'Memuat…' : `${users.total} pengguna`}
                    </span>
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                router.get(usersIndex().url);
                            }}
                        >
                            <X />
                            Hapus filter
                        </Button>
                    )}
                </div>

                {users.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Tidak ada pengguna yang cocok"
                        description="Coba kata kunci atau peran lain."
                    />
                ) : (
                    <div
                        className={`overflow-x-auto rounded-lg border transition-opacity ${loading ? 'opacity-60' : ''}`}
                    >
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Nama
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Peran
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
                                {users.data.map((user) => (
                                    <tr key={user.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {user.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {user.email}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            {roleLabel(user.role)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {user.is_active ? (
                                                    <Badge variant="secondary">
                                                        Aktif
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-destructive"
                                                    >
                                                        Nonaktif
                                                    </Badge>
                                                )}
                                                {!user.email_verified && (
                                                    <Badge variant="outline">
                                                        Email belum
                                                        terverifikasi
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setEditing(user)}
                                            >
                                                Ubah
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination links={users.links} />
            </div>

            <CreateUserDialog open={creating} onOpenChange={setCreating} />
            <EditUserDialog
                user={editing}
                roles={roles}
                isSelf={editing?.id === page.props.auth.user.id}
                onOpenChange={(open) => !open && setEditing(null)}
            />
        </>
    );
}

AdminUsers.layout = {
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Pengguna', href: usersIndex() },
    ],
};
