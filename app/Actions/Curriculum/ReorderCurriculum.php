<?php

namespace App\Actions\Curriculum;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the whole curriculum order in one transaction: module order, lesson
 * order within each module, and lessons moved between modules.
 *
 * The payload must list exactly the course's current (not deleted) modules
 * and lessons, each once — otherwise nothing is changed.
 */
class ReorderCurriculum
{
    /**
     * @param  list<array{id: int, lessons: list<int>}>  $modules
     */
    public function handle(Course $course, array $modules): void
    {
        DB::transaction(function () use ($course, $modules): void {
            $moduleIds = $course->modules()->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $lessonIds = $course->lessons()->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            $givenModules = collect($modules)->pluck('id')->map(fn ($id) => (int) $id);
            $givenLessons = collect($modules)->flatMap(fn (array $module) => $module['lessons'])->map(fn ($id) => (int) $id);

            if (
                $givenModules->sort()->values()->all() !== $moduleIds
                || $givenLessons->sort()->values()->all() !== $lessonIds
                || $givenLessons->duplicates()->isNotEmpty()
            ) {
                throw ValidationException::withMessages([
                    'modules' => 'Urutan kurikulum tidak valid atau sudah berubah. Muat ulang halaman lalu coba lagi.',
                ]);
            }

            // Positions come from the order of the payload, not from its keys.
            $modulePosition = 0;

            foreach ($modules as $module) {
                Module::whereKey($module['id'])->update(['position' => $modulePosition++]);

                $lessonPosition = 0;

                foreach ($module['lessons'] as $lessonId) {
                    Lesson::whereKey($lessonId)->update([
                        'module_id' => $module['id'],
                        'position' => $lessonPosition++,
                    ]);
                }
            }

            $course->touch();
        });
    }
}
