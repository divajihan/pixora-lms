import { AssignmentDialog } from '@/components/admin/curriculum/assignment-dialog';
import {
    closestCorners,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { Announcements, DragEndEvent, DragOverEvent } from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { Head, Link, router } from '@inertiajs/react';
import {
    ClipboardList,
    FolderPlus,
    ListTree,
    Pencil,
    Plus,
    RotateCcw,
    Save,
    Trophy,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CourseTabs } from '@/components/admin/course-tabs';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import {
    findModuleIndexOfLesson,
    lessonKey,
    moduleKey,
    moveLesson,
    moveModule,
    orderSignature,
    parseKey,
    toPayload,
} from '@/components/admin/curriculum/curriculum-state';
import { LessonDialog } from '@/components/admin/curriculum/lesson-dialog';
import { ModuleDialog } from '@/components/admin/curriculum/module-dialog';
import {
    SortableLesson,
    SortableModule,
} from '@/components/admin/curriculum/sortable-items';
import { EmptyState } from '@/components/admin/empty-state';
import { StatusBadge } from '@/components/admin/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDuration } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import {
    curriculum as curriculumRoute,
    edit,
    index as coursesIndex,
} from '@/routes/admin/courses';
import { reorder } from '@/routes/admin/courses/curriculum';
import { destroy as destroyLesson } from '@/routes/admin/lessons';
import { destroy as destroyModule } from '@/routes/admin/modules';
import type {
    CourseStatusValue,
    CurriculumLesson,
    CurriculumModule,
    CurriculumTotals,
    LessonStatusValue,
    LessonTypeValue,
    Option,
} from '@/types/admin';
import type { AssignmentForm } from '@/types/assignments';

type Props = {
    course: {
        id: number;
        title: string;
        slug: string;
        status: CourseStatusValue;
        status_label: string;
        drip_enabled: boolean;
    };
    modules: CurriculumModule[];
    totals: CurriculumTotals;
    lessonTypes: Option<LessonTypeValue>[];
    lessonStatuses: Option<LessonStatusValue>[];
    finalProject: AssignmentForm | null;
    assignmentStatuses: { value: string; label: string }[];
    assignmentMaxImages: number;
};

type DeleteTarget =
    | { kind: 'module'; module: CurriculumModule }
    | { kind: 'lesson'; lesson: CurriculumLesson };

export default function AdminCurriculum({
    course,
    modules,
    totals,
    lessonTypes,
    lessonStatuses,
    finalProject,
    assignmentStatuses,
    assignmentMaxImages,
}: Props) {
    const [items, setItems] = useState<CurriculumModule[]>(modules);
    const [saving, setSaving] = useState(false);
    const [moduleDialog, setModuleDialog] = useState<{
        open: boolean;
        module?: CurriculumModule;
    }>({ open: false });
    const [lessonDialog, setLessonDialog] = useState<{
        open: boolean;
        module?: CurriculumModule;
        lesson?: CurriculumLesson;
    }>({ open: false });
    const [assignmentDialog, setAssignmentDialog] = useState<{
        open: boolean;
        lesson: CurriculumLesson | null;
    }>({ open: false, lesson: null });
    const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);
    const [deleting, setDeleting] = useState(false);

    // Server data is the source of truth after every request.
    useEffect(() => setItems(modules), [modules]);

    const dirty = useMemo(
        () => orderSignature(items) !== orderSignature(modules),
        [items, modules],
    );

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const titleOf = (key: string | number) => {
        const { kind, id } = parseKey(key);

        return kind === 'module'
            ? (items.find((module) => module.id === id)?.title ?? 'modul')
            : (items
                  .flatMap((module) => module.lessons)
                  .find((lesson) => lesson.id === id)?.title ?? 'lesson');
    };

    const announcements: Announcements = {
        onDragStart: ({ active }) => `Mengambil ${titleOf(active.id)}.`,
        onDragOver: ({ active, over }) =>
            over
                ? `${titleOf(active.id)} berada di atas ${titleOf(over.id)}.`
                : `${titleOf(active.id)} tidak berada di area urutan.`,
        onDragEnd: ({ active, over }) =>
            over
                ? `${titleOf(active.id)} dipindahkan. Simpan urutan untuk menerapkan.`
                : `${titleOf(active.id)} dilepas.`,
        onDragCancel: ({ active }) =>
            `Batal memindahkan ${titleOf(active.id)}.`,
    };

    const handleDragOver = ({ active, over }: DragOverEvent) => {
        if (!over) {
            return;
        }

        const from = parseKey(active.id);
        const to = parseKey(over.id);

        if (from.kind !== 'lesson') {
            return;
        }

        setItems((current) => {
            const fromModule = findModuleIndexOfLesson(current, from.id);
            const targetModuleIndex =
                to.kind === 'module'
                    ? current.findIndex((module) => module.id === to.id)
                    : findModuleIndexOfLesson(current, to.id);

            if (
                fromModule === -1 ||
                targetModuleIndex === -1 ||
                fromModule === targetModuleIndex
            ) {
                return current;
            }

            const target = current[targetModuleIndex];
            const overIndex =
                to.kind === 'lesson'
                    ? target.lessons.findIndex((lesson) => lesson.id === to.id)
                    : target.lessons.length;

            return moveLesson(current, from.id, target.id, overIndex);
        });
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const from = parseKey(active.id);
        const to = parseKey(over.id);

        setItems((current) => {
            if (from.kind === 'module' && to.kind === 'module') {
                return moveModule(
                    current,
                    current.findIndex((module) => module.id === from.id),
                    current.findIndex((module) => module.id === to.id),
                );
            }

            if (from.kind === 'lesson' && to.kind === 'lesson') {
                const moduleIndex = findModuleIndexOfLesson(current, to.id);

                if (moduleIndex === -1) {
                    return current;
                }

                const targetIndex = current[moduleIndex].lessons.findIndex(
                    (lesson) => lesson.id === to.id,
                );

                return moveLesson(
                    current,
                    from.id,
                    current[moduleIndex].id,
                    targetIndex,
                );
            }

            return current;
        });
    };

    const saveOrder = () => {
        router.put(
            reorder(course.id).url,
            { modules: toPayload(items) },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        const url =
            deleteTarget.kind === 'module'
                ? destroyModule(deleteTarget.module.id).url
                : destroyLesson(deleteTarget.lesson.id).url;

        router.delete(url, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <>
            <Head title={`Kurikulum: ${course.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <h1 className="truncate text-2xl font-semibold tracking-tight">
                                Kurikulum
                            </h1>
                            <StatusBadge status={course.status} />
                        </div>
                        <p className="truncate text-muted-foreground">
                            {course.title}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={edit(course.id)}>
                                <Pencil />
                                Info kursus
                            </Link>
                        </Button>
                        <Button
                            onClick={() => setModuleDialog({ open: true })}
                            disabled={dirty}
                        >
                            <FolderPlus />
                            Modul baru
                        </Button>
                    </div>
                </div>

                <CourseTabs
                    courseId={course.id}
                    active="curriculum"
                    dripEnabled={course.drip_enabled}
                />

                <section
                    aria-label="Proyek akhir"
                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                >
                    <div className="flex items-start gap-3">
                        <Trophy className="mt-0.5 size-5 text-muted-foreground" />
                        <div>
                            <p className="font-medium">
                                {finalProject
                                    ? finalProject.title
                                    : 'Proyek akhir'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {finalProject
                                    ? `${finalProject.status_label} · tenggat ${finalProject.due_offset_days} hari setelah ${course.drip_enabled ? 'batch berakhir' : 'siswa terdaftar'} · ${finalProject.submissions_count ?? 0} pengumpulan`
                                    : 'Belum ada proyek akhir untuk kursus ini.'}
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setAssignmentDialog({ open: true, lesson: null })
                        }
                        disabled={dirty}
                    >
                        <ClipboardList />
                        {finalProject
                            ? 'Ubah proyek akhir'
                            : 'Tambah proyek akhir'}
                    </Button>
                </section>

                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        ['Modul', totals.modules],
                        ['Lesson', totals.lessons],
                        ['Lesson terbit', totals.published_lessons],
                        [
                            'Total durasi',
                            formatDuration(totals.duration_seconds),
                        ],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-lg border p-3">
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="text-lg font-semibold">{value}</dd>
                        </div>
                    ))}
                </dl>

                {dirty && (
                    <div
                        role="status"
                        className="sticky top-2 z-20 flex flex-col gap-2 rounded-lg border border-primary/40 bg-background p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p className="text-sm">
                            Urutan berubah dan belum disimpan. Ubah/tambah
                            konten dinonaktifkan sampai urutan disimpan atau
                            dibatalkan.
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setItems(modules)}
                                disabled={saving}
                            >
                                <RotateCcw />
                                Batalkan
                            </Button>
                            <Button
                                size="sm"
                                onClick={saveOrder}
                                disabled={saving}
                            >
                                {saving ? <Spinner /> : <Save />}
                                Simpan urutan
                            </Button>
                        </div>
                    </div>
                )}

                {items.length === 0 ? (
                    <EmptyState
                        icon={ListTree}
                        title="Kurikulum masih kosong"
                        description="Mulai dengan membuat modul, lalu tambahkan lesson video atau kelas live di dalamnya."
                        action={
                            <Button
                                size="sm"
                                onClick={() => setModuleDialog({ open: true })}
                            >
                                <FolderPlus />
                                Modul pertama
                            </Button>
                        }
                    />
                ) : (
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCorners}
                        onDragOver={handleDragOver}
                        onDragEnd={handleDragEnd}
                        accessibility={{
                            announcements,
                            screenReaderInstructions: {
                                draggable:
                                    'Tekan spasi atau Enter untuk mengambil. Gunakan panah untuk memindahkan, spasi atau Enter untuk melepas, Escape untuk batal.',
                            },
                        }}
                    >
                        <SortableContext
                            items={items.map((module) => moduleKey(module.id))}
                            strategy={verticalListSortingStrategy}
                        >
                            <div className="flex flex-col gap-4">
                                {items.map((module, moduleIndex) => (
                                    <SortableModule
                                        key={module.id}
                                        module={module}
                                        index={moduleIndex}
                                        total={items.length}
                                        editingLocked={dirty}
                                        onEdit={() =>
                                            setModuleDialog({
                                                open: true,
                                                module,
                                            })
                                        }
                                        onDelete={() =>
                                            setDeleteTarget({
                                                kind: 'module',
                                                module,
                                            })
                                        }
                                        onAddLesson={() =>
                                            setLessonDialog({
                                                open: true,
                                                module,
                                            })
                                        }
                                        onMove={(delta) =>
                                            setItems((current) =>
                                                moveModule(
                                                    current,
                                                    moduleIndex,
                                                    moduleIndex + delta,
                                                ),
                                            )
                                        }
                                    >
                                        <SortableContext
                                            items={module.lessons.map(
                                                (lesson) =>
                                                    lessonKey(lesson.id),
                                            )}
                                            strategy={
                                                verticalListSortingStrategy
                                            }
                                        >
                                            {module.lessons.length === 0 ? (
                                                <div className="flex flex-col items-center gap-2 rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                                                    Belum ada lesson. Seret
                                                    lesson ke sini atau
                                                    tambahkan baru.
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setLessonDialog({
                                                                open: true,
                                                                module,
                                                            })
                                                        }
                                                        disabled={dirty}
                                                    >
                                                        <Plus />
                                                        Tambah lesson
                                                    </Button>
                                                </div>
                                            ) : (
                                                <ol className="flex flex-col gap-2">
                                                    {module.lessons.map(
                                                        (
                                                            lesson,
                                                            lessonIndex,
                                                        ) => (
                                                            <SortableLesson
                                                                key={lesson.id}
                                                                lesson={lesson}
                                                                index={
                                                                    lessonIndex
                                                                }
                                                                total={
                                                                    module
                                                                        .lessons
                                                                        .length
                                                                }
                                                                modules={items}
                                                                showDrip={
                                                                    course.drip_enabled
                                                                }
                                                                editingLocked={
                                                                    dirty
                                                                }
                                                                onEdit={() =>
                                                                    setLessonDialog(
                                                                        {
                                                                            open: true,
                                                                            module,
                                                                            lesson,
                                                                        },
                                                                    )
                                                                }
                                                                onDelete={() =>
                                                                    setDeleteTarget(
                                                                        {
                                                                            kind: 'lesson',
                                                                            lesson,
                                                                        },
                                                                    )
                                                                }
                                                                onMove={(
                                                                    delta,
                                                                ) =>
                                                                    setItems(
                                                                        (
                                                                            current,
                                                                        ) =>
                                                                            moveLesson(
                                                                                current,
                                                                                lesson.id,
                                                                                module.id,
                                                                                lessonIndex +
                                                                                    delta,
                                                                            ),
                                                                    )
                                                                }
                                                                onAssignment={() =>
                                                                    setAssignmentDialog(
                                                                        {
                                                                            open: true,
                                                                            lesson,
                                                                        },
                                                                    )
                                                                }
                                                                onMoveToModule={(
                                                                    moduleId,
                                                                ) =>
                                                                    setItems(
                                                                        (
                                                                            current,
                                                                        ) =>
                                                                            moveLesson(
                                                                                current,
                                                                                lesson.id,
                                                                                moduleId,
                                                                            ),
                                                                    )
                                                                }
                                                            />
                                                        ),
                                                    )}
                                                </ol>
                                            )}
                                        </SortableContext>
                                    </SortableModule>
                                ))}
                            </div>
                        </SortableContext>
                    </DndContext>
                )}
            </div>

            <ModuleDialog
                courseId={course.id}
                module={moduleDialog.module}
                open={moduleDialog.open}
                onOpenChange={(open) =>
                    setModuleDialog((current) => ({ ...current, open }))
                }
            />

            <AssignmentDialog
                courseId={course.id}
                lessonId={assignmentDialog.lesson?.id ?? null}
                lessonTitle={assignmentDialog.lesson?.title}
                assignment={
                    assignmentDialog.lesson
                        ? assignmentDialog.lesson.assignment
                        : finalProject
                }
                statuses={assignmentStatuses}
                maxImages={assignmentMaxImages}
                dripEnabled={course.drip_enabled}
                open={assignmentDialog.open}
                onOpenChange={(open) =>
                    setAssignmentDialog((current) => ({ ...current, open }))
                }
            />

            {lessonDialog.module && (
                <LessonDialog
                    module={lessonDialog.module}
                    lesson={lessonDialog.lesson}
                    open={lessonDialog.open}
                    onOpenChange={(open) =>
                        setLessonDialog((current) => ({ ...current, open }))
                    }
                    types={lessonTypes}
                    statuses={lessonStatuses}
                    dripEnabled={course.drip_enabled}
                />
            )}

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={
                    deleteTarget?.kind === 'module'
                        ? 'Hapus modul?'
                        : 'Hapus lesson?'
                }
                description={
                    deleteTarget?.kind === 'module'
                        ? `Modul "${deleteTarget.module.title}" dan ${deleteTarget.module.lessons.length} lesson di dalamnya akan diarsipkan (soft delete).`
                        : `Lesson "${deleteTarget?.kind === 'lesson' ? deleteTarget.lesson.title : ''}" akan diarsipkan (soft delete).`
                }
                processing={deleting}
                onConfirm={confirmDelete}
            />
        </>
    );
}

AdminCurriculum.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dasbor', href: dashboard() },
        { title: 'Kursus', href: coursesIndex() },
        { title: props.course.title, href: edit(props.course.id) },
        { title: 'Kurikulum', href: curriculumRoute(props.course.id) },
    ],
});
