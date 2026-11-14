<?php

namespace App\Actions\Learning;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reverts a completed lesson to in-progress (idempotent) and recalculates progress.
 */
class UnmarkLessonCompleted
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
            LessonProgress::where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->where('status', LessonProgressStatus::Completed)
                ->update(['status' => LessonProgressStatus::InProgress, 'completed_at' => null, 'updated_at' => now()]);

            return $this->recalculate->handle($enrollment);
        });
    }
}
