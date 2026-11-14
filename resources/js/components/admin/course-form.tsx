import { useForm } from '@inertiajs/react';
import { ImageUp, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { CourseThumbnail } from '@/components/catalog/course-thumbnail';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatRupiah } from '@/lib/format';
import { slugify } from '@/lib/slug';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/admin/courses';
import type { AdminCourse, CategoryItem } from '@/types/admin';

type CourseFormData = {
    title: string;
    slug: string;
    summary: string;
    description: string;
    category_id: string;
    is_free: boolean;
    price: string;
    thumbnail: File | null;
    remove_thumbnail: boolean;
    drip_enabled: boolean;
    access_duration_days: string;
    certificate_enabled: boolean;
};

const NONE = 'none';

export function CourseForm({
    course,
    categories,
    stacked = false,
}: {
    course?: AdminCourse;
    categories: CategoryItem[];
    /** Single column (used when the page already has a side column). */
    stacked?: boolean;
}) {
    const isEdit = course !== undefined;
    const [slugTouched, setSlugTouched] = useState(isEdit);

    const form = useForm<CourseFormData>({
        title: course?.title ?? '',
        slug: course?.slug ?? '',
        summary: course?.summary ?? '',
        description: course?.description ?? '',
        category_id: course?.category_id ? String(course.category_id) : '',
        is_free: course?.is_free ?? false,
        price: course && !course.is_free ? String(course.price) : '',
        thumbnail: null,
        remove_thumbnail: false,
        drip_enabled: course?.drip_enabled ?? false,
        access_duration_days: course?.access_duration_days
            ? String(course.access_duration_days)
            : '',
        certificate_enabled: course?.certificate_enabled ?? true,
    });

    const { data, setData, errors, processing } = form;

    const previewUrl = useMemo(
        () => (data.thumbnail ? URL.createObjectURL(data.thumbnail) : null),
        [data.thumbnail],
    );

    useEffect(
        () => () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        },
        [previewUrl],
    );

    const shownThumbnail =
        previewUrl ??
        (data.remove_thumbnail ? null : (course?.thumbnail_url ?? null));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isEdit) {
            // Files require multipart POST + method spoofing for updates.
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(update(course.id).url, {
                forceFormData: true,
                preserveScroll: true,
            });

            return;
        }

        form.submit(store(), { forceFormData: true });
    };

    const categoryLabel =
        categories.find((category) => String(category.id) === data.category_id)
            ?.name ?? 'Tanpa kategori';

    return (
        <form
            onSubmit={submit}
            className={cn(
                'grid min-w-0 gap-6',
                !stacked && 'lg:grid-cols-[minmax(0,1fr)_320px]',
            )}
            noValidate
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi kursus</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Judul</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(event) => {
                                    const title = event.target.value;
                                    setData((current) => ({
                                        ...current,
                                        title,
                                        slug: slugTouched
                                            ? current.slug
                                            : slugify(title),
                                    }));
                                }}
                                maxLength={200}
                                required
                                aria-invalid={Boolean(errors.title)}
                                autoFocus={!isEdit}
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">Slug (alamat URL)</Label>
                            <div className="flex items-center rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                                <span className="pl-3 text-sm whitespace-nowrap text-muted-foreground">
                                    /kursus/
                                </span>
                                <input
                                    id="slug"
                                    value={data.slug}
                                    onChange={(event) => {
                                        setSlugTouched(true);
                                        setData(
                                            'slug',
                                            event.target.value.toLowerCase(),
                                        );
                                    }}
                                    className="h-9 w-full bg-transparent pr-3 text-sm outline-none"
                                    maxLength={200}
                                    aria-invalid={Boolean(errors.slug)}
                                    aria-describedby="slug-help"
                                />
                            </div>
                            <p
                                id="slug-help"
                                className="text-xs text-muted-foreground"
                            >
                                Dibuat otomatis dari judul. Kosongkan untuk
                                membuat ulang.
                                {isEdit &&
                                    course?.status === 'published' &&
                                    ' Mengubah slug kursus yang sudah terbit akan mengubah tautannya.'}
                            </p>
                            <InputError message={errors.slug} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="summary">Ringkasan</Label>
                            <Textarea
                                id="summary"
                                value={data.summary}
                                onChange={(event) =>
                                    setData('summary', event.target.value)
                                }
                                maxLength={500}
                                rows={2}
                                aria-describedby="summary-help"
                                aria-invalid={Boolean(errors.summary)}
                            />
                            <p
                                id="summary-help"
                                className="text-xs text-muted-foreground"
                            >
                                Tampil di kartu katalog dan meta deskripsi (
                                {data.summary.length}/500).
                            </p>
                            <InputError message={errors.summary} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Deskripsi</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                rows={8}
                                aria-invalid={Boolean(errors.description)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Teks biasa; baris baru dipertahankan.
                            </p>
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category">Kategori</Label>
                            <Select
                                value={data.category_id || NONE}
                                onValueChange={(value) =>
                                    setData(
                                        'category_id',
                                        value === NONE ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="category"
                                    className="w-full sm:w-72"
                                >
                                    <SelectValue>{categoryLabel}</SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        Tanpa kategori
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.category_id} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Harga & akses</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <label className="flex items-start gap-3">
                            <Checkbox
                                checked={data.is_free}
                                onCheckedChange={(checked) =>
                                    setData('is_free', checked === true)
                                }
                                className="mt-0.5"
                            />
                            <span>
                                <span className="text-sm font-medium">
                                    Kursus gratis
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Harga otomatis Rp 0.
                                </span>
                            </span>
                        </label>

                        {!data.is_free && (
                            <div className="grid gap-2">
                                <Label htmlFor="price">Harga (Rp)</Label>
                                <Input
                                    id="price"
                                    type="number"
                                    inputMode="numeric"
                                    min={1000}
                                    step={1000}
                                    value={data.price}
                                    onChange={(event) =>
                                        setData('price', event.target.value)
                                    }
                                    className="w-full sm:w-56"
                                    aria-describedby="price-help"
                                    aria-invalid={Boolean(errors.price)}
                                />
                                <p
                                    id="price-help"
                                    className="text-xs text-muted-foreground"
                                >
                                    {data.price
                                        ? formatRupiah(Number(data.price))
                                        : 'Bilangan bulat rupiah, tanpa titik.'}
                                </p>
                                <InputError message={errors.price} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="access_duration_days">
                                Durasi akses (hari)
                            </Label>
                            <Input
                                id="access_duration_days"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                value={data.access_duration_days}
                                onChange={(event) =>
                                    setData(
                                        'access_duration_days',
                                        event.target.value,
                                    )
                                }
                                className="w-full sm:w-56"
                                placeholder="Seumur hidup"
                                aria-describedby="access-help"
                                aria-invalid={Boolean(
                                    errors.access_duration_days,
                                )}
                            />
                            <p
                                id="access-help"
                                className="text-xs text-muted-foreground"
                            >
                                Kosongkan untuk akses seumur hidup. Dihitung
                                sejak siswa terdaftar.
                            </p>
                            <InputError message={errors.access_duration_days} />
                        </div>

                        <label className="flex items-start gap-3">
                            <Checkbox
                                checked={data.drip_enabled}
                                onCheckedChange={(checked) =>
                                    setData('drip_enabled', checked === true)
                                }
                                className="mt-0.5"
                            />
                            <span>
                                <span className="text-sm font-medium">
                                    Buka lesson bertahap (drip)
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Lesson terbuka sesuai "hari buka"
                                    masing-masing.
                                </span>
                            </span>
                        </label>

                        <label className="flex items-start gap-3">
                            <Checkbox
                                checked={data.certificate_enabled}
                                onCheckedChange={(checked) =>
                                    setData(
                                        'certificate_enabled',
                                        checked === true,
                                    )
                                }
                                className="mt-0.5"
                            />
                            <span>
                                <span className="text-sm font-medium">
                                    Sertifikat kelulusan
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Siswa mendapat sertifikat setelah lulus.
                                </span>
                            </span>
                        </label>
                    </CardContent>
                </Card>
            </div>

            <div className="flex flex-col gap-6">
                <Card className="gap-4">
                    <CardHeader>
                        <CardTitle>Gambar sampul</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <CourseThumbnail
                            src={shownThumbnail}
                            title={data.title || 'Kursus'}
                            className="rounded-md border"
                        />
                        <Label
                            htmlFor="thumbnail"
                            className="flex cursor-pointer items-center justify-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm font-normal hover:bg-accent"
                        >
                            <ImageUp className="size-4" />
                            {shownThumbnail ? 'Ganti gambar' : 'Unggah gambar'}
                        </Label>
                        <input
                            id="thumbnail"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="sr-only"
                            onChange={(event) => {
                                setData((current) => ({
                                    ...current,
                                    thumbnail: event.target.files?.[0] ?? null,
                                    remove_thumbnail: false,
                                }));
                            }}
                            aria-describedby="thumbnail-help"
                        />
                        <p
                            id="thumbnail-help"
                            className="text-xs text-muted-foreground"
                        >
                            JPG, PNG, atau WEBP, maksimal 2 MB. Rasio 16:9
                            disarankan.
                        </p>
                        {shownThumbnail && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    setData((current) => ({
                                        ...current,
                                        thumbnail: null,
                                        remove_thumbnail: true,
                                    }))
                                }
                            >
                                <Trash2 />
                                Hapus gambar
                            </Button>
                        )}
                        <InputError message={errors.thumbnail} />
                        {form.progress && (
                            <progress
                                value={form.progress.percentage}
                                max={100}
                                className="w-full"
                            >
                                {form.progress.percentage}%
                            </progress>
                        )}
                    </CardContent>
                </Card>

                <div className="sticky bottom-4 flex flex-col gap-2 rounded-lg border bg-background p-3 shadow-sm lg:static lg:border-0 lg:p-0 lg:shadow-none">
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {isEdit ? 'Simpan perubahan' : 'Buat kursus'}
                    </Button>
                    {form.hasErrors && (
                        <p
                            role="alert"
                            className="text-center text-sm text-destructive"
                        >
                            Periksa kembali isian yang ditandai.
                        </p>
                    )}
                    {form.recentlySuccessful && (
                        <p className="text-center text-sm text-muted-foreground">
                            Tersimpan.
                        </p>
                    )}
                </div>
            </div>
        </form>
    );
}
