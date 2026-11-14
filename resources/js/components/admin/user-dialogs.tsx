import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
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
import { store, update } from '@/routes/admin/users';
import type { AdminUser, Option } from '@/types/admin';

export function CreateUserDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ name: '', email: '', role: 'instructor' });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when opened
    }, [open]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store(), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <DialogHeader>
                        <DialogTitle>Akun baru</DialogTitle>
                        <DialogDescription>
                            Sistem membuat kata sandi sementara yang ditampilkan
                            sekali setelah akun dibuat.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="new-name">Nama</Label>
                        <Input
                            id="new-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            autoFocus
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-email">Email</Label>
                        <Input
                            id="new-email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            aria-invalid={Boolean(form.errors.email)}
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-role">Peran</Label>
                        <Select
                            value={form.data.role}
                            onValueChange={(value) =>
                                form.setData('role', value)
                            }
                        >
                            <SelectTrigger id="new-role" className="w-full">
                                <SelectValue>
                                    {form.data.role === 'staff'
                                        ? 'Staf'
                                        : 'Instruktur'}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="instructor">
                                    Instruktur
                                </SelectItem>
                                <SelectItem value="staff">Staf</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.role} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Buat akun
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function EditUserDialog({
    user,
    roles,
    isSelf,
    onOpenChange,
}: {
    user: AdminUser | null;
    roles: Option[];
    isSelf: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        role: 'student',
        is_active: true,
        headline: '',
        bio: '',
    });

    useEffect(() => {
        if (user) {
            form.setData({
                role: user.role ?? 'student',
                is_active: user.is_active,
                headline: user.headline ?? '',
                bio: user.bio ?? '',
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset when another user is opened
    }, [user?.id]);

    if (!user) {
        return null;
    }

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(update(user.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const roleLabel =
        roles.find((role) => role.value === form.data.role)?.label ??
        form.data.role;

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <DialogHeader>
                        <DialogTitle>{user.name}</DialogTitle>
                        <DialogDescription>{user.email}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-role">Peran</Label>
                        <Select
                            value={form.data.role}
                            onValueChange={(value) =>
                                form.setData('role', value)
                            }
                        >
                            <SelectTrigger id="edit-role" className="w-full">
                                <SelectValue>{roleLabel}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem
                                        key={role.value}
                                        value={role.value}
                                    >
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.role} />
                    </div>
                    <label className="flex items-start gap-3">
                        <Checkbox
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                            disabled={isSelf}
                            className="mt-0.5"
                        />
                        <span>
                            <span className="text-sm font-medium">
                                Akun aktif
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {isSelf
                                    ? 'Anda tidak dapat menonaktifkan akun sendiri.'
                                    : 'Akun nonaktif tidak bisa masuk dan sesinya diakhiri.'}
                            </span>
                        </span>
                    </label>
                    <InputError message={form.errors.is_active} />

                    {form.data.role === 'instructor' && (
                        <fieldset className="grid gap-4 rounded-md border p-4">
                            <legend className="px-1 text-sm font-medium">
                                Profil instruktur
                            </legend>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-headline">Headline</Label>
                                <Input
                                    id="edit-headline"
                                    value={form.data.headline}
                                    onChange={(e) =>
                                        form.setData('headline', e.target.value)
                                    }
                                    maxLength={150}
                                    placeholder="Contoh: Desainer produk & pengajar UI/UX"
                                />
                                <InputError message={form.errors.headline} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-bio">Bio</Label>
                                <Textarea
                                    id="edit-bio"
                                    value={form.data.bio}
                                    onChange={(e) =>
                                        form.setData('bio', e.target.value)
                                    }
                                    rows={4}
                                    maxLength={2000}
                                />
                                <InputError message={form.errors.bio} />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Tampil di halaman detail kursus.
                            </p>
                        </fieldset>
                    )}

                    <DialogFooter>
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
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
