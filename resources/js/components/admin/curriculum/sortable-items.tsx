import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
    ArrowDown,
    ArrowUp,
    CalendarClock,
    Eye,
    GripVertical,
    MoreHorizontal,
    Pencil,
    Plus,
    Radio,
    Trash2,
    Video,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/admin/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime, formatDuration } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { CurriculumLesson, CurriculumModule } from '@/types/admin';
import { lessonKey, moduleKey } from './curriculum-state';

function DragHandle({
    label,
    listeners,
    attributes,
}: {
    label: string;
    listeners: ReturnType<typeof useSortable>['listeners'];
    attributes: ReturnType<typeof useSortable>['attributes'];
}) {
    return (
        <button
            type="button"
            className="flex size-8 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
            aria-label={label}
            {...attributes}
            {...listeners}
        >
            <GripVertical className="size-4" />
        </button>
    );
}

export function SortableModule({
    module,
    index,
    total,
    editingLocked,
    onEdit,
    onDelete,
    onAddLesson,
    onMove,
    children,
}: {
    module: CurriculumModule;
    index: number;
    total: number;
    editingLocked: boolean;
    onEdit: () => void;
    onDelete: () => void;
    onAddLesson: () => void;
    onMove: (delta: number) => void;
    children: ReactNode;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: moduleKey(module.id),
        data: { type: 'module' },
    });

    const duration = module.lessons.reduce(
        (sum, lesson) => sum + (lesson.duration_seconds ?? 0),
        0,
    );

    return (
        <section
            ref={setNodeRef}
            style={{ transform: CSS.Translate.toString(transform), transition }}
            className={cn(
                'rounded-lg border bg-card',
                isDragging && 'z-10 opacity-80 shadow-lg',
            )}
            aria-label={module.title}
        >
            <header className="flex items-center gap-2 border-b p-2 sm:p-3">
                <DragHandle
                    label={`Seret untuk memindahkan modul ${module.title}`}
                    attributes={attributes}
                    listeners={listeners}
                />
                <div className="min-w-0 flex-1">
                    <h2 className="truncate font-semibold">{module.title}</h2>
                    <p className="text-xs text-muted-foreground">
                        {module.lessons.length} lesson ·{' '}
                        {formatDuration(duration)}
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onAddLesson}
                    disabled={editingLocked}
                >
                    <Plus />
                    <span className="hidden sm:inline">Lesson</span>
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            aria-label={`Aksi modul ${module.title}`}
                        >
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onSelect={onEdit}
                            disabled={editingLocked}
                        >
                            <Pencil />
                            Ubah judul
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => onMove(-1)}
                            disabled={index === 0}
                        >
                            <ArrowUp />
                            Naikkan
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => onMove(1)}
                            disabled={index === total - 1}
                        >
                            <ArrowDown />
                            Turunkan
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={onDelete}
                            disabled={editingLocked}
                        >
                            <Trash2 />
                            Hapus modul
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </header>
            <div className="p-2 sm:p-3">{children}</div>
        </section>
    );
}

export function SortableLesson({
    lesson,
    index,
    total,
    modules,
    showDrip,
    editingLocked,
    onEdit,
    onDelete,
    onMove,
    onMoveToModule,
}: {
    lesson: CurriculumLesson;
    index: number;
    total: number;
    modules: CurriculumModule[];
    showDrip: boolean;
    editingLocked: boolean;
    onEdit: () => void;
    onDelete: () => void;
    onMove: (delta: number) => void;
    onMoveToModule: (moduleId: number) => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: lessonKey(lesson.id),
        data: { type: 'lesson' },
    });

    const TypeIcon = lesson.type === 'live' ? Radio : Video;
    const otherModules = modules.filter(
        (module) => module.id !== lesson.module_id,
    );

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Translate.toString(transform), transition }}
            className={cn(
                'flex items-center gap-2 rounded-md border bg-background p-2',
                isDragging && 'z-10 opacity-80 shadow-md',
                lesson.status === 'draft' && 'border-dashed',
            )}
        >
            <DragHandle
                label={`Seret untuk memindahkan lesson ${lesson.title}`}
                attributes={attributes}
                listeners={listeners}
            />
            <TypeIcon
                className="size-4 shrink-0 text-muted-foreground"
                aria-label={lesson.type === 'live' ? 'Kelas live' : 'Video'}
            />
            <div className="min-w-0 flex-1">
                <button
                    type="button"
                    onClick={onEdit}
                    disabled={editingLocked}
                    className="block max-w-full truncate text-left text-sm font-medium hover:text-primary hover:underline disabled:no-underline disabled:hover:text-current"
                >
                    {lesson.title}
                </button>
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    <span>{formatDuration(lesson.duration_seconds)}</span>
                    {showDrip && <span>Hari ke-{lesson.drip_day_offset}</span>}
                    {lesson.live?.starts_at && (
                        <span className="inline-flex items-center gap-1">
                            <CalendarClock className="size-3" />
                            {formatDateTime(lesson.live.starts_at)}
                        </span>
                    )}
                </div>
            </div>
            <div className="hidden items-center gap-1 sm:flex">
                {lesson.is_preview && (
                    <Badge variant="secondary">
                        <Eye />
                        Pratinjau
                    </Badge>
                )}
                {lesson.status === 'draft' && <StatusBadge status="draft" />}
            </div>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label={`Aksi lesson ${lesson.title}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        onSelect={onEdit}
                        disabled={editingLocked}
                    >
                        <Pencil />
                        Ubah lesson
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onSelect={() => onMove(-1)}
                        disabled={index === 0}
                    >
                        <ArrowUp />
                        Naikkan
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onSelect={() => onMove(1)}
                        disabled={index === total - 1}
                    >
                        <ArrowDown />
                        Turunkan
                    </DropdownMenuItem>
                    {otherModules.length > 0 && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel>
                                Pindah ke modul
                            </DropdownMenuLabel>
                            {otherModules.map((module) => (
                                <DropdownMenuItem
                                    key={module.id}
                                    onSelect={() => onMoveToModule(module.id)}
                                >
                                    {module.title}
                                </DropdownMenuItem>
                            ))}
                        </>
                    )}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={onDelete}
                        disabled={editingLocked}
                    >
                        <Trash2 />
                        Hapus lesson
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </li>
    );
}
