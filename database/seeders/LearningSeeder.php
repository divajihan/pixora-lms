<?php

namespace Database\Seeders;

use App\Actions\Enrollments\GrantEnrollment;
use App\Actions\Learning\ComputeLessonAccess;
use App\Actions\Learning\RecalculateEnrollmentProgress;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonUnlockOverride;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Phase 4 demo data, relative to today: every drip course gets three batches
 * (finished, running, upcoming); free courses get sample students with
 * progress; the running batch of the first drip course gets one batch
 * override and one per-student override. Idempotent. Development only.
 */
class LearningSeeder extends Seeder
{
    public function __construct(
        private readonly GrantEnrollment $grant,
        private readonly ComputeLessonAccess $access,
        private readonly RecalculateEnrollmentProgress $recalculate,
    ) {}

    public function run(): void
    {
        $today = CarbonImmutable::today();
        $students = $this->students();

        Course::where('drip_enabled', true)->orderBy('id')->get()->each(function (Course $course, int $index) use ($today, $students) {
            $days = max(7, (int) $course->lessons()->max('drip_day_offset') + 1);
            $number = 42 + $index * 3;

            $batches = [
                'finished' => $this->batch($course, 'Batch '.$number, $today->subDays($days + 14), $days),
                'running' => $this->batch($course, 'Batch '.($number + 1), $today->subDays(3), $days, capacity: 30),
                'upcoming' => $this->batch($course, 'Batch '.($number + 2), $today->addDays(10), $days, capacity: 25, closesOn: $today->addDays(9)),
            ];

            if (! $course->is_free || ! $course->isPublished()) {
                return; // paid enrollments come from real (sandbox) orders
            }

            // Finished batch: most students done; running batch: partial progress.
            foreach ($students->slice(0, 3) as $i => $student) {
                $this->enroll($student, $course, $batches['finished'], completeRatio: [1.0, 0.8, 0.5][$i % 3], enrolledAt: $today->subDays($days + 16));
            }

            foreach ($students->slice(3) as $i => $student) {
                $this->enroll($student, $course, $batches['running'], completeRatio: [1.0, 0.5, 0.0, 0.7, 0.3][$i % 5], enrolledAt: $today->subDays(5));
            }

            if ($index === 0) {
                $main = User::where('email', 'student@pixora.test')->first();

                if ($main) {
                    $enrollment = $this->enroll($main, $course, $batches['running'], completeRatio: 0.5, enrolledAt: $today->subDays(4));
                    $this->demoOverrides($course, $batches['running'], $enrollment);
                }
            }
        });

        // Non-drip free course: everything opens at enrollment.
        $main = User::where('email', 'student@pixora.test')->first();
        $openCourse = Course::where('drip_enabled', false)->where('is_free', true)->published()->orderBy('id')->first();

        if ($main && $openCourse) {
            $this->enroll($main, $openCourse, null, completeRatio: 0.3, enrolledAt: $today->subDays(10));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function students()
    {
        $names = ['Andi Pratama', 'Sari Wulandari', 'Dimas Saputra', 'Putri Ayu', 'Rizky Hidayat', 'Nadia Lestari', 'Fajar Nugroho', 'Maya Anggraini'];

        return collect($names)->values()->map(function (string $name, int $i) {
            $user = User::updateOrCreate(['email' => 'siswa'.($i + 1).'@pixora.test'], ['name' => $name, 'password' => 'password']);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now(), 'is_active' => true])->save();
            $user->syncRoles([Role::Student->value]);

            return $user;
        });
    }

    private function batch(Course $course, string $name, CarbonImmutable $startsOn, int $days, ?int $capacity = null, ?CarbonImmutable $closesOn = null): Batch
    {
        $batch = Batch::withTrashed()->updateOrCreate(['course_id' => $course->id, 'name' => $name], [
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $startsOn->addDays($days - 1)->toDateString(),
            'registration_closes_on' => $closesOn?->toDateString(),
            'capacity' => $capacity,
            'is_published' => true,
        ]);

        if ($batch->trashed()) {
            $batch->restore();
        }

        return $batch;
    }

    /**
     * Enrolls (idempotent) and completes the first $completeRatio of the lessons
     * that are open for the student right now, then recalculates progress.
     */
    private function enroll(User $user, Course $course, ?Batch $batch, float $completeRatio, CarbonImmutable $enrolledAt): Enrollment
    {
        $enrollment = $this->grant->handle($user, $course, null, $batch);
        $enrollment->forceFill(['enrolled_at' => $enrolledAt, 'status' => EnrollmentStatus::Active])->save();

        if ($enrollment->lessonProgress()->exists()) {
            return $enrollment;
        }

        $lessons = Lesson::where('course_id', $course->id)->published()->whereHas('module')->orderBy('drip_day_offset')->orderBy('id')->get();
        $access = $this->access->forEnrollment($enrollment->load('batch', 'course'), $lessons);
        $open = $lessons->filter(fn (Lesson $lesson) => $access[$lesson->id]->unlocked)->values();
        $done = $open->take((int) floor($open->count() * $completeRatio));

        foreach ($done as $i => $lesson) {
            LessonProgress::create([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'status' => LessonProgressStatus::Completed,
                'completed_at' => $enrolledAt->addDays($i)->setTime(20, 0),
            ]);
        }

        $this->recalculate->handle($enrollment);

        return $enrollment->refresh();
    }

    /**
     * Running batch: the next locked lesson opens a day early for the whole
     * batch; the one after that opens today for the demo student only.
     */
    private function demoOverrides(Course $course, Batch $batch, Enrollment $enrollment): void
    {
        $lessons = Lesson::where('course_id', $course->id)->published()->where('is_preview', false)
            ->where('drip_day_offset', '>', 3)->orderBy('drip_day_offset')->orderBy('id')->take(2)->get();
        $instructor = User::where('email', 'instructor@pixora.test')->first();

        if ($lessons->count() < 2) {
            return;
        }

        [$batchLesson, $studentLesson] = [$lessons[0], $lessons[1]];

        LessonUnlockOverride::updateOrCreate(
            ['lesson_id' => $batchLesson->id, 'batch_id' => $batch->id, 'enrollment_id' => null],
            ['unlock_at' => $batch->dayStart($batchLesson->drip_day_offset)->subDay(), 'created_by' => $instructor?->id],
        );

        LessonUnlockOverride::updateOrCreate(
            ['lesson_id' => $studentLesson->id, 'batch_id' => $batch->id, 'enrollment_id' => $enrollment->id],
            ['unlock_at' => CarbonImmutable::today(config('app.timezone')), 'created_by' => $instructor?->id],
        );
    }
}
