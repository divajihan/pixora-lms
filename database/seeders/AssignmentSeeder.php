<?php

namespace Database\Seeders;

use App\Actions\Assignments\ComputeAssignmentDeadline;
use App\Enums\AssignmentStatus;
use App\Enums\CommentAuthorRole;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Attachment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 5 demo data (development only, idempotent):
 * - every published/archived course with lessons gets assignments on its
 *   first two published lessons and a final project;
 * - students with enrollments get sample submissions relative to their
 *   real deadlines: on time, late, and on time with instructor feedback
 *   (including a "drawn-over" feedback image), plus one still editable.
 * Images are small generated PNG placeholders on the private disk.
 */
class AssignmentSeeder extends Seeder
{
    public function __construct(private readonly ComputeAssignmentDeadline $deadlines) {}

    public function run(): void
    {
        Course::query()->whereHas('lessons')->with('instructors')->orderBy('id')->get()
            ->each(fn (Course $course) => $this->seedCourse($course));
    }

    private function seedCourse(Course $course): void
    {
        $lessons = Lesson::query()
            ->where('course_id', $course->id)
            ->where('status', LessonStatus::Published)
            ->where('is_preview', false)
            ->whereHas('module')
            ->orderBy('drip_day_offset')->orderBy('id')
            ->take(2)->get();

        $assignments = $lessons->values()->map(fn (Lesson $lesson, int $i) => $this->assignment($course, $lesson, [
            'title' => $i === 0 ? 'Latihan: '.Str::lower($lesson->title) : 'Praktik mandiri',
            'instructions_html' => '<p>Kerjakan latihan dari lesson <strong>'.e($lesson->title).'</strong>, lalu unggah hasilnya dalam bentuk gambar (foto atau tangkapan layar).</p><ul><li>Maksimal 3 gambar</li><li>Tambahkan catatan bila ada yang ingin ditanyakan</li></ul>',
            'due_offset_days' => $i === 0 ? 1 : 3,
            'max_images' => 3,
            'is_required' => $i === 0,
        ]));

        $project = $this->assignment($course, null, [
            'title' => 'Proyek akhir: '.$course->title,
            'instructions_html' => '<p>Buat satu karya utuh yang menerapkan materi kursus, lalu unggah 1–5 gambar hasilnya.</p>',
            'due_offset_days' => 7,
            'max_images' => 5,
            'is_required' => true,
        ]);

        $instructor = $course->instructors->first();

        if ($instructor === null || $assignments->isEmpty()) {
            return;
        }

        $enrollments = Enrollment::query()
            ->where('course_id', $course->id)
            ->where('status', EnrollmentStatus::Active)
            ->with(['user', 'batch', 'course'])
            ->orderBy('id')
            ->get();

        foreach ($enrollments as $index => $enrollment) {
            foreach ([...$assignments->all(), $project] as $assignmentIndex => $assignment) {
                $this->sampleSubmission($assignment->load('lesson'), $enrollment, $index + $assignmentIndex, $instructor);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assignment(Course $course, ?Lesson $lesson, array $attributes): Assignment
    {
        $assignment = Assignment::withTrashed()
            ->where('course_id', $course->id)
            ->when($lesson, fn ($query) => $query->where('lesson_id', $lesson?->id), fn ($query) => $query->whereNull('lesson_id'))
            ->first() ?? new Assignment(['course_id' => $course->id, 'lesson_id' => $lesson?->id]);

        if (! $assignment->exists) {
            $assignment->fill([...$attributes, 'status' => AssignmentStatus::Published])->save();
        } elseif ($assignment->trashed()) {
            $assignment->restore();
        }

        return $assignment;
    }

    /**
     * Deterministic mix per student: 0 = on time + feedback, 1 = on time
     * (awaiting review), 2 = late, 3 = nothing yet. Only for assignments that
     * are open; submission time is derived from the real deadline.
     */
    private function sampleSubmission(Assignment $assignment, Enrollment $enrollment, int $seed, User $instructor): void
    {
        if (Submission::where('assignment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->exists()) {
            return;
        }

        $deadline = $this->deadlines->for($assignment, $enrollment);
        $now = CarbonImmutable::now();

        // Not open yet for this student (reference date in the future).
        if ($deadline->reference->gt($now)) {
            return;
        }

        $kind = $seed % 4;
        $passed = $deadline->hasPassed();

        $submittedAt = match (true) {
            $kind === 3 => null,
            $kind === 2 && $passed => $deadline->effective->addHours(5)->min($now),
            default => $deadline->reference->addHours(10)->min($now->subMinutes(30)),
        };

        if ($submittedAt === null || $submittedAt->gt($now) || ($kind === 2 && $submittedAt->lte($deadline->effective))) {
            return;
        }

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'body' => $kind === 0 ? 'Saya masih ragu dengan bagian warnanya.' : null,
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => $submittedAt,
        ]);
        $this->attachImage($submission->attachments(), "submissions/{$assignment->id}/{$enrollment->id}", 'karya-'.$enrollment->id.'.png', [52, 120, 200], $enrollment->user);

        // Feedback only after the deadline, only for on-time work.
        if ($kind === 0 && $passed) {
            $comment = $submission->comments()->create([
                'user_id' => $instructor->id,
                'author_role' => CommentAuthorRole::Instructor,
                'body' => 'Komposisinya sudah rapi. Lihat coretan saya: perbesar judul dan beri ruang di sisi kanan.',
            ]);
            $comment->forceFill(['created_at' => $deadline->effective->addHours(10)->min($now), 'updated_at' => $deadline->effective->addHours(10)->min($now)])->saveQuietly();
            $this->attachImage($comment->attachments(), "submission-comments/{$submission->id}", 'coretan.png', [220, 80, 60], $instructor);

            $submission->update([
                'status' => SubmissionStatus::Reviewed,
                'reviewed_at' => $comment->created_at,
                'reviewed_by' => $instructor->id,
            ]);
        }
    }

    /**
     * Writes a small generated PNG (+ thumbnail) to the private assignments disk.
     *
     * @template TOwner of Model
     *
     * @param  MorphMany<Attachment, TOwner>  $relation
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     */
    private function attachImage(MorphMany $relation, string $directory, string $name, array $rgb, User $uploader): void
    {
        $disk = (string) config('pixora.assignments.disk');
        $random = Str::random(40);
        [$width, $height] = [800, 600];

        $relation->create([
            'disk' => $disk,
            'path' => $path = "{$directory}/{$random}.png",
            'thumbnail_path' => $thumb = "{$directory}/{$random}_thumb.png",
            'original_name' => $name,
            'mime' => 'image/png',
            'size' => strlen($bytes = $this->png($width, $height, $rgb)),
            'width' => $width,
            'height' => $height,
            'uploaded_by' => $uploader->id,
        ]);

        Storage::disk($disk)->put($path, $bytes);
        Storage::disk($disk)->put($thumb, $this->png(240, 180, $rgb));
    }

    /**
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     */
    private function png(int $width, int $height, array $rgb): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 245, 245, 240));
        $color = (int) imagecolorallocate($image, ...$rgb);
        imagefilledrectangle($image, (int) ($width * 0.1), (int) ($height * 0.15), (int) ($width * 0.6), (int) ($height * 0.55), $color);
        imagefilledellipse($image, (int) ($width * 0.72), (int) ($height * 0.68), (int) ($width * 0.3), (int) ($width * 0.3), (int) imagecolorallocate($image, 40, 40, 40));
        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }
}
