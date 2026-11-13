import { useForm, router, usePage } from '@inertiajs/react';
import { UserMinus, UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store } from '@/routes/admin/courses/instructors';
import type {
    AssignedInstructor,
    InstructorCandidate,
    Option,
} from '@/types/admin';

export function CourseInstructorsCard({
    courseId,
    instructors,
    available,
    roles,
}: {
    courseId: number;
    instructors: AssignedInstructor[];
    available: InstructorCandidate[];
    roles: Option<'lead' | 'assistant'>[];
}) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const form = useForm({
        user_id: '',
        role: instructors.length === 0 ? 'lead' : 'assistant',
    });
    const [removing, setRemoving] = useState<AssignedInstructor | null>(null);
    const [processingRemove, setProcessingRemove] = useState(false);

    const add = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store(courseId), {
            preserveScroll: true,
            onSuccess: () => form.reset('user_id'),
        });
    };

    const changeRole = (instructor: AssignedInstructor, role: string) => {
        router.post(
            store(courseId).url,
            { user_id: instructor.id, role },
            { preserveScroll: true },
        );
    };

    const remove = () => {
        if (!removing) {
            return;
        }

        router.delete(destroy([courseId, removing.id]).url, {
            preserveScroll: true,
            onStart: () => setProcessingRemove(true),
            onFinish: () => {
                setProcessingRemove(false);
                setRemoving(null);
            },
        });
    };

    const roleLabel = (value: string | null) =>
        roles.find((role) => role.value === value)?.label ?? '–';
    const selected = available.find(
        (user) => String(user.id) === form.data.user_id,
    );

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>Instruktur</CardTitle>
                <CardDescription>
                    Minimal satu instruktur utama sebelum kursus diterbitkan.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {instructors.length === 0 ? (
                    <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                        Belum ada instruktur yang ditugaskan.
                    </p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {instructors.map((instructor) => (
                            <li
                                key={instructor.id}
                                className="flex flex-col gap-2 p-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {instructor.name}
                                        {instructor.role === 'lead' && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                Utama
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {instructor.email}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Select
                                        value={instructor.role ?? 'assistant'}
                                        onValueChange={(value) =>
                                            changeRole(instructor, value)
                                        }
                                    >
                                        <SelectTrigger
                                            size="sm"
                                            aria-label={`Peran ${instructor.name}`}
                                            className="w-44"
                                        >
                                            <SelectValue>
                                                {roleLabel(instructor.role)}
                                            </SelectValue>
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
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Lepas ${instructor.name}`}
                                        onClick={() => setRemoving(instructor)}
                                    >
                                        <UserMinus />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {errors.user_id && !form.errors.user_id && (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.user_id}
                    </p>
                )}

                <form onSubmit={add} className="grid gap-2">
                    <Label htmlFor="instructor-select">Tambah instruktur</Label>
                    {available.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            Tidak ada instruktur lain yang tersedia. Buat akun
                            instruktur di menu Pengguna.
                        </p>
                    ) : (
                        <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <Select
                                value={form.data.user_id}
                                onValueChange={(value) =>
                                    form.setData('user_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="instructor-select"
                                    className="w-full sm:col-span-2"
                                >
                                    <SelectValue placeholder="Pilih instruktur">
                                        {selected
                                            ? selected.name
                                            : 'Pilih instruktur'}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    {available.map((user) => (
                                        <SelectItem
                                            key={user.id}
                                            value={String(user.id)}
                                        >
                                            {user.name} · {user.email}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={form.data.role}
                                onValueChange={(value) =>
                                    form.setData('role', value)
                                }
                            >
                                <SelectTrigger
                                    aria-label="Peran di kursus"
                                    className="w-full"
                                >
                                    <SelectValue>
                                        {roleLabel(form.data.role)}
                                    </SelectValue>
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
                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={!form.data.user_id || form.processing}
                            >
                                {form.processing ? <Spinner /> : <UserPlus />}
                                Tambah
                            </Button>
                        </div>
                    )}
                    <InputError
                        message={form.errors.user_id ?? form.errors.role}
                    />
                </form>
            </CardContent>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Lepas instruktur?"
                description={`${removing?.name ?? ''} tidak lagi ditugaskan pada kursus ini.`}
                confirmLabel="Lepas"
                processing={processingRemove}
                onConfirm={remove}
            />
        </Card>
    );
}
