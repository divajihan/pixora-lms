<?php

namespace App\Actions\Learning;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a lesson completed for an enrollment (idempotent) and recalculates
 * progress. Only lessons the student can currently view may be marked.
 */
class MarkLessonCompleted
{
    public function __construct(
        private readonly ComputeLessonAccess $access,
        private readonly RecalculateEnrollmentProgress $recalculate,
    ) {}

    public function handle(Enrollment $enrollment, Lesson $lesson): int
    {
        if (! $this->access->canViewLesson($enrollment, $lesson)) {
            throw ValidationException::withMessages(['lesson' => 'Lesson ini belum terbuka untuk Anda.']);
        }

        return DB::transaction(function () use ($enrollment, $lesson): int {
            $progress = LessonProgress::firstOrNew(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);

            if ($progress->status !== LessonProgressStatus::Completed) {
                $progress->fill(['status' => LessonProgressStatus::Completed, 'completed_at' => now()])->save();
            }

            return $this->recalculate->handle($enrollment);
        });
    }
}
