<?php

namespace App\Actions\Courses;

use App\Enums\CourseInstructorRole;
use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a course after checking the publish rules:
 * - at least one published lesson (in a module that is not deleted);
 * - at least one lead instructor;
 * - drip courses: at least one published batch.
 */
class PublishCourse
{
    /**
     * Human-readable reasons why the course cannot be published (empty = OK).
     *
     * @return list<string>
     */
    public function blockers(Course $course): array
    {
        $reasons = [];

        $hasPublishedLesson = $course->lessons()
            ->published()
            ->whereHas('module')
            ->exists();

        if (! $hasPublishedLesson) {
            $reasons[] = 'Kursus belum memiliki lesson berstatus terbit. Tambahkan dan terbitkan minimal 1 lesson di halaman kurikulum.';
        }

        $hasLead = $course->instructors()
            ->wherePivot('role', CourseInstructorRole::Lead->value)
            ->exists();

        if (! $hasLead) {
            $reasons[] = 'Kursus belum memiliki instruktur utama. Tugaskan minimal 1 instruktur dengan peran "Instruktur utama".';
        }

        if ($course->drip_enabled && ! $course->batches()->published()->exists()) {
            $reasons[] = 'Kursus dengan jadwal bertahap (drip) harus memiliki minimal 1 batch yang dipublikasikan. Tambahkan batch di tab Batch.';
        }

        return $reasons;
    }

    /**
     * @throws ValidationException when a publish rule is not met.
     */
    public function handle(Course $course): Course
    {
        $reasons = $this->blockers($course);

        if ($reasons !== []) {
            throw ValidationException::withMessages(['status' => $reasons]);
        }

        $course->forceFill([
            'status' => CourseStatus::Published,
            // Keep the original publish date when re-publishing.
            'published_at' => $course->published_at ?? now(),
        ])->save();

        return $course;
    }
}
