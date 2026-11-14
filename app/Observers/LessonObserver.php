<?php

namespace App\Observers;

use App\Enums\LessonStatus;
use App\Jobs\RecalculateCourseProgress;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * Keeps enrollment progress correct when the set of published lessons changes
 * (publish, unpublish, delete, restore) — whichever screen caused it.
 */
class LessonObserver
{
    public function created(Lesson $lesson): void
    {
        if ($lesson->status === LessonStatus::Published) {
            $this->recalculate($lesson);
        }
    }

    /**
     * Note: not "saved" + wasRecentlyCreated — that flag stays true on an instance
     * that is updated later, which would hide status changes.
     */
    public function updated(Lesson $lesson): void
    {
        if ($lesson->wasChanged('status')) {
            $this->recalculate($lesson);
        }
    }

    public function deleted(Lesson $lesson): void
    {
        $this->recalculate($lesson);
    }

    public function restored(Lesson $lesson): void
    {
        $this->recalculate($lesson);
    }

    private function recalculate(Lesson $lesson): void
    {
        if (Enrollment::where('course_id', $lesson->course_id)->exists()) {
            // Dispatch once the surrounding transaction (e.g. SaveLesson) has committed,
            // so the queued job never reads uncommitted lesson data.
            $courseId = $lesson->course_id;
            DB::afterCommit(fn () => RecalculateCourseProgress::dispatch($courseId));
        }
    }
}
