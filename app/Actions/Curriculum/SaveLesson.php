<?php

namespace App\Actions\Curriculum;

use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Models\Batch;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\Video\VideoProvider;
use App\Support\HtmlSanitizer;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a lesson (and its live session) from validated data.
 * - video URL → provider id only (video_ref + video_provider);
 * - summary_html is sanitised before saving;
 * - the slug is generated once (unique per course) and kept stable;
 * - a published lesson's drip day cannot move later if that would re-lock it
 *   in a batch with students (opened lessons never lock again).
 */
class SaveLesson
{
    public function __construct(
        private readonly VideoProvider $videoProvider,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated LessonRequest data.
     */
    public function handle(Module $module, ?Lesson $lesson, array $data): Lesson
    {
        return DB::transaction(function () use ($module, $lesson, $data): Lesson {
            $type = LessonType::from((string) $data['type']);
            $isVideo = $type === LessonType::Video;

            if ($lesson !== null) {
                $this->guardOffsetChange($lesson, (int) $data['drip_day_offset']);
            }

            $lesson ??= new Lesson([
                'course_id' => $module->course_id,
                'module_id' => $module->id,
                'slug' => $this->slug($module->course_id, (string) $data['title']),
                'position' => (int) $module->lessons()->max('position') + ($module->lessons()->exists() ? 1 : 0),
            ]);

            $lesson->fill([
                'title' => $data['title'],
                'type' => $type,
                'status' => $data['status'],
                'video_provider' => $isVideo ? $this->videoProvider->key() : null,
                'video_ref' => $isVideo ? $this->videoProvider->parseReference((string) $data['video_url']) : null,
                'summary_html' => $this->sanitizer->clean($data['summary_html'] ?? null),
                'drip_day_offset' => (int) $data['drip_day_offset'],
                'is_preview' => (bool) ($data['is_preview'] ?? false),
                'duration_seconds' => $data['duration_seconds'] ?? null,
            ])->save();

            if ($type === LessonType::Live) {
                $lesson->liveSession()->updateOrCreate([], [
                    'starts_at' => $data['live_starts_at'],
                    'ends_at' => $data['live_ends_at'] ?? null,
                    'platform' => $data['live_platform'] ?? null,
                    'meeting_url' => $data['live_meeting_url'] ?? null,
                    'notes' => $data['live_notes'] ?? null,
                ]);
            } else {
                $lesson->liveSession()->delete();
            }

            return $lesson;
        });
    }

    /**
     * Rejects a later drip_day_offset when the lesson is already open on its
     * default schedule in a batch that has students but would be locked again
     * under the new offset. Moving it earlier is always allowed.
     */
    private function guardOffsetChange(Lesson $lesson, int $newOffset): void
    {
        if ($newOffset <= $lesson->drip_day_offset
            || $lesson->status !== LessonStatus::Published
            || ! $lesson->course()->where('drip_enabled', true)->exists()) {
            return;
        }

        $now = now();

        $relocked = Batch::query()
            ->where('course_id', $lesson->course_id)
            ->whereHas('enrollments')
            ->where('starts_on', '<=', $now->toDateString())
            ->orderBy('starts_on')
            ->get()
            ->filter(fn (Batch $batch) => $batch->dayStart($lesson->drip_day_offset)->lte($now)
                && $batch->dayStart($newOffset)->gt($now));

        if ($relocked->isNotEmpty()) {
            throw ValidationException::withMessages([
                'drip_day_offset' => 'Lesson ini sudah terbuka untuk siswa di '.$relocked->pluck('name')->join(', ', ' dan ')
                    .'. Hari buka tidak bisa dimundurkan karena lesson akan terkunci lagi. Lesson yang sudah terbuka tetap terbuka; '
                    .'atur jadwal batch mendatang lewat Kelola kelas bila perlu.',
            ]);
        }
    }

    private function slug(int $courseId, string $title): string
    {
        return UniqueSlug::make($title, fn (string $slug) => Lesson::withTrashed()
            ->where('course_id', $courseId)
            ->where('slug', $slug)
            ->exists());
    }
}
