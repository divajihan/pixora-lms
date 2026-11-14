<?php

namespace App\Actions\Learning;

use App\Enums\LessonProgressStatus;
use App\Enums\LessonStatus;
use App\Models\Enrollment;
use App\Models\Lesson;

/**
 * progress_percent = completed published lessons / published lessons
 * (rounded down; 0 when the course has none). Only published lessons in live
 * modules count. Never marks the enrollment completed (Phase 6 decides that).
 */
class RecalculateEnrollmentProgress
{
    public function handle(Enrollment $enrollment): int
    {
        $published = Lesson::query()
            ->where('course_id', $enrollment->course_id)
            ->where('status', LessonStatus::Published)
            ->whereHas('module')
            ->select('id');

        $total = (clone $published)->count();

        $completed = $total === 0 ? 0 : $enrollment->lessonProgress()
            ->where('status', LessonProgressStatus::Completed)
            ->whereIn('lesson_id', $published)
            ->count();

        $percent = $total === 0 ? 0 : intdiv($completed * 100, $total);

        if ($enrollment->progress_percent !== $percent) {
            $enrollment->forceFill(['progress_percent' => $percent])->save();
        }

        return $percent;
    }
}
