<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates an assignment (admin curriculum).
 * - One assignment per lesson, one final project per course: a soft-deleted
 *   one in the same slot is restored instead of creating a second row.
 * - instructions_html is sanitised like lesson summaries ("rich_text").
 * - delete() refuses assignments that already have submissions (set them to
 *   draft instead) and soft-deletes the rest.
 */
class SaveAssignment
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * @param  array{lesson_id?: int|string|null, title: string, instructions_html?: string|null, due_offset_days: int|string, max_images: int|string, is_required: bool, status: string}  $data
     */
    public function handle(Course $course, ?Assignment $assignment, array $data): Assignment
    {
        return DB::transaction(function () use ($course, $assignment, $data): Assignment {
            if ($assignment === null) {
                $lessonId = isset($data['lesson_id']) ? (int) $data['lesson_id'] : null;
                $assignment = $this->slot($course, $lessonId);
            }

            $assignment->fill([
                'title' => $data['title'],
                'instructions_html' => $this->sanitizer->clean($data['instructions_html'] ?? null),
                'due_offset_days' => (int) $data['due_offset_days'],
                'max_images' => (int) $data['max_images'],
                'is_required' => (bool) $data['is_required'],
                'status' => $data['status'],
            ])->save();

            return $assignment;
        });
    }

    public function delete(Assignment $assignment): void
    {
        if ($assignment->submissions()->exists()) {
            throw ValidationException::withMessages([
                'assignment' => 'Tugas ini sudah memiliki pengumpulan dan tidak dapat dihapus. Ubah statusnya menjadi draf agar tidak tampil ke siswa.',
            ]);
        }

        $assignment->delete();
    }

    /**
     * New (or restored soft-deleted) assignment for the lesson / final project slot.
     */
    private function slot(Course $course, ?int $lessonId): Assignment
    {
        if ($lessonId !== null) {
            $lesson = Lesson::where('course_id', $course->id)->whereKey($lessonId)->first();

            if ($lesson === null) {
                throw ValidationException::withMessages(['lesson_id' => 'Lesson tidak ditemukan di kursus ini.']);
            }
        }

        $existing = Assignment::withTrashed()
            ->where('course_id', $course->id)
            ->when($lessonId !== null, fn ($query) => $query->where('lesson_id', $lessonId), fn ($query) => $query->whereNull('lesson_id'))
            ->lockForUpdate()
            ->first();

        if ($existing !== null && ! $existing->trashed()) {
            throw ValidationException::withMessages([
                'title' => $lessonId !== null ? 'Lesson ini sudah memiliki tugas.' : 'Kursus ini sudah memiliki proyek akhir.',
            ]);
        }

        if ($existing !== null) {
            $existing->restore();

            return $existing;
        }

        return new Assignment(['course_id' => $course->id, 'lesson_id' => $lessonId]);
    }
}
