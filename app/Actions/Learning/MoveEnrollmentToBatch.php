<?php

namespace App\Actions\Learning;

use App\Enums\LessonStatus;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonUnlockOverride;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin moves an enrollment to another batch of the same course.
 * - The student's overrides in the old batch are deleted.
 * - Lessons already open for the student stay open: if the new batch would
 *   open them later, a per-student override (unlock_at = now) is created.
 * - Logged (no activity-log package).
 */
class MoveEnrollmentToBatch
{
    public function __construct(private readonly ComputeLessonAccess $access) {}

    public function handle(Enrollment $enrollment, Batch $target, User $admin): Enrollment
    {
        if ($target->course_id !== $enrollment->course_id) {
            throw ValidationException::withMessages(['batch_id' => 'Batch tujuan harus dari kursus yang sama.']);
        }

        if ($target->id === $enrollment->batch_id) {
            throw ValidationException::withMessages(['batch_id' => 'Siswa sudah berada di batch ini.']);
        }

        return DB::transaction(function () use ($enrollment, $target, $admin): Enrollment {
            /** @var Enrollment $enrollment */
            $enrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $oldBatchId = $enrollment->batch_id;

            $lessons = Lesson::where('course_id', $enrollment->course_id)->where('status', LessonStatus::Published)->get();
            $before = $this->access->forEnrollment($enrollment, $lessons);

            LessonUnlockOverride::where('enrollment_id', $enrollment->id)->delete();

            $enrollment->forceFill(['batch_id' => $target->id])->save();
            $enrollment->setRelation('batch', $target);

            $after = $this->access->forEnrollment($enrollment, $lessons);

            foreach ($lessons as $lesson) {
                if ($before[$lesson->id]->unlocked && ! $after[$lesson->id]->unlocked) {
                    LessonUnlockOverride::create([
                        'lesson_id' => $lesson->id,
                        'batch_id' => $target->id,
                        'enrollment_id' => $enrollment->id,
                        'unlock_at' => now(),
                        'created_by' => $admin->id,
                    ]);
                }
            }

            logger()->info('Enrollment moved to another batch', [
                'enrollment_id' => $enrollment->id,
                'from_batch_id' => $oldBatchId,
                'to_batch_id' => $target->id,
                'admin_id' => $admin->id,
            ]);

            return $enrollment;
        });
    }
}
