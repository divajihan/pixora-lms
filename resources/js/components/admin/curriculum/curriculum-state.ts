import { arrayMove } from '@dnd-kit/sortable';
import type { CurriculumModule } from '@/types/admin';

/**
 * Pure helpers for reordering the curriculum (used by drag & drop and by the
 * keyboard/menu alternatives).
 */
export const moduleKey = (id: number) => `module-${id}`;
export const lessonKey = (id: number) => `lesson-${id}`;

export function parseKey(key: string | number): {
    kind: 'module' | 'lesson';
    id: number;
} {
    const [kind, id] = String(key).split('-');

    return { kind: kind === 'module' ? 'module' : 'lesson', id: Number(id) };
}

export function orderSignature(modules: CurriculumModule[]): string {
    return modules
        .map(
            (module) =>
                `${module.id}:${module.lessons.map((lesson) => lesson.id).join(',')}`,
        )
        .join('|');
}

export function toPayload(modules: CurriculumModule[]) {
    return modules.map((module) => ({
        id: module.id,
        lessons: module.lessons.map((lesson) => lesson.id),
    }));
}

export function findModuleIndexOfLesson(
    modules: CurriculumModule[],
    lessonId: number,
): number {
    return modules.findIndex((module) =>
        module.lessons.some((lesson) => lesson.id === lessonId),
    );
}

export function moveModule(
    modules: CurriculumModule[],
    from: number,
    to: number,
): CurriculumModule[] {
    if (to < 0 || to >= modules.length || from === to) {
        return modules;
    }

    return arrayMove(modules, from, to);
}

/**
 * Moves a lesson to `targetModuleId` at `targetIndex` (end when undefined).
 */
export function moveLesson(
    modules: CurriculumModule[],
    lessonId: number,
    targetModuleId: number,
    targetIndex?: number,
): CurriculumModule[] {
    const fromIndex = findModuleIndexOfLesson(modules, lessonId);
    const toIndex = modules.findIndex((module) => module.id === targetModuleId);

    if (fromIndex === -1 || toIndex === -1) {
        return modules;
    }

    const lesson = modules[fromIndex].lessons.find(
        (item) => item.id === lessonId,
    )!;

    if (fromIndex === toIndex) {
        const lessons = modules[fromIndex].lessons;
        const oldIndex = lessons.findIndex((item) => item.id === lessonId);
        const newIndex = Math.max(
            0,
            Math.min(targetIndex ?? lessons.length - 1, lessons.length - 1),
        );

        if (oldIndex === newIndex) {
            return modules;
        }

        return modules.map((module, index) =>
            index === fromIndex
                ? { ...module, lessons: arrayMove(lessons, oldIndex, newIndex) }
                : module,
        );
    }

    return modules.map((module, index) => {
        if (index === fromIndex) {
            return {
                ...module,
                lessons: module.lessons.filter((item) => item.id !== lessonId),
            };
        }

        if (index === toIndex) {
            const lessons = [...module.lessons];
            lessons.splice(targetIndex ?? lessons.length, 0, {
                ...lesson,
                module_id: module.id,
            });

            return { ...module, lessons };
        }

        return module;
    });
}
