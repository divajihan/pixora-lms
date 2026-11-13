import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/admin/modules';
import type { CurriculumModule } from '@/types/admin';

/**
 * Create (module undefined) or rename a module.
 */
export function ModuleDialog({
    courseId,
    module,
    open,
    onOpenChange,
}: {
    courseId: number;
    module?: CurriculumModule;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ title: module?.title ?? '' });

    useEffect(() => {
        if (open) {
            form.setData('title', module?.title ?? '');
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when (re)opened
    }, [open, module?.id]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(module ? update(module.id) : store(courseId), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {module ? 'Ubah modul' : 'Modul baru'}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="module-title">Judul modul</Label>
                        <Input
                            id="module-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            maxLength={200}
                            autoFocus
                            placeholder="Contoh: Modul 1: Pengenalan"
                            aria-invalid={Boolean(form.errors.title)}
                        />
                        <InputError message={form.errors.title} />
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
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
