<?php

namespace App\Http\Controllers;

use App\Actions\Learning\ComputeLessonAccess;
use App\Actions\Learning\MoveEnrollmentToBatch;
use App\Actions\Learning\SetLessonUnlockOverride;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonUnlockOverride;
use App\Models\Module;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Running a class (/kelas/...): batches, students with progress, and lesson
 * unlock schedules per batch or per student. Assigned instructors and admins
 * (CoursePolicy::manageClass); staff cannot.
 */
class ClassController extends Controller
{
    public function batches(Course $course): Response
    {
        Gate::authorize('manageClass', $course);

        return Inertia::render('classes/batches', [
            'course' => $this->courseProps($course),
            'batches' => BatchResource::collection($course->batches()->withSeatsTaken()->get())->resolve(),
        ]);
    }

    public function students(Request $request, Course $course, Batch $batch): Response
    {
        Gate::authorize('manageClass', $course);
        $this->ensureBelongs($course, $batch);

        $search = Str::of((string) $request->query('q', ''))->squish()->limit(100, '')->toString();
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $students = $batch->enrollments()
            ->with('user:id,name,email')
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]))))
            ->orderByDesc('progress_percent')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Enrollment $enrollment) => [
                'id' => $enrollment->id,
                'name' => $enrollment->user->name,
                'email' => $enrollment->user->email,
                'status' => $enrollment->status->value,
                'status_label' => $enrollment->status->label(),
                'progress_percent' => $enrollment->progress_percent,
                'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
            ]);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('classes/students', [
            'course' => $this->courseProps($course),
            'batch' => BatchResource::make($batch)->resolve(),
            'students' => $students,
            'filters' => ['q' => $search ?: null],
            'otherBatches' => $user->can('update', $course)
                ? $course->batches()->whereKeyNot($batch->id)->get(['id', 'name', 'starts_on'])->map(fn (Batch $other) => ['id' => $other->id, 'name' => $other->name])->values()
                : [],
        ]);
    }

    public function schedule(Course $course, Batch $batch, ComputeLessonAccess $access): Response
    {
        Gate::authorize('manageClass', $course);
        $this->ensureBelongs($course, $batch);

        return Inertia::render('classes/schedule', [
            'course' => $this->courseProps($course),
            'batch' => BatchResource::make($batch)->resolve(),
            'student' => null,
            'modules' => $this->scheduleRows($course, $batch, null, $access),
        ]);
    }

    public function studentSchedule(Course $course, Batch $batch, Enrollment $enrollment, ComputeLessonAccess $access): Response
    {
        Gate::authorize('manageClass', $course);
        $this->ensureBelongs($course, $batch, $enrollment);

        $enrollment->load(['user:id,name,email', 'course', 'batch']);

        return Inertia::render('classes/schedule', [
            'course' => $this->courseProps($course),
            'batch' => BatchResource::make($batch)->resolve(),
            'student' => ['id' => $enrollment->id, 'name' => $enrollment->user->name, 'email' => $enrollment->user->email],
            'modules' => $this->scheduleRows($course, $batch, $enrollment, $access),
        ]);
    }

    public function setOverride(Request $request, Course $course, Batch $batch, Lesson $lesson, SetLessonUnlockOverride $set): RedirectResponse
    {
        return $this->applyOverride($request, $course, $batch, null, $lesson, $set);
    }

    public function setStudentOverride(Request $request, Course $course, Batch $batch, Enrollment $enrollment, Lesson $lesson, SetLessonUnlockOverride $set): RedirectResponse
    {
        return $this->applyOverride($request, $course, $batch, $enrollment, $lesson, $set);
    }

    public function resetOverride(Course $course, Batch $batch, Lesson $lesson, SetLessonUnlockOverride $set): RedirectResponse
    {
        return $this->removeOverride($course, $batch, null, $lesson, $set);
    }

    public function resetStudentOverride(Course $course, Batch $batch, Enrollment $enrollment, Lesson $lesson, SetLessonUnlockOverride $set): RedirectResponse
    {
        return $this->removeOverride($course, $batch, $enrollment, $lesson, $set);
    }

    /**
     * Admin only (courses.manage): move a student to another batch of the course.
     */
    public function moveEnrollment(Request $request, Course $course, Batch $batch, Enrollment $enrollment, MoveEnrollmentToBatch $move): RedirectResponse
    {
        Gate::authorize('update', $course);
        $this->ensureBelongs($course, $batch, $enrollment);

        $data = $request->validate(['batch_id' => ['required', 'integer']], [], ['batch_id' => 'batch tujuan']);
        $target = $course->batches()->whereKey($data['batch_id'])->firstOrFail();

        /** @var User $admin */
        $admin = $request->user();
        $move->handle($enrollment, $target, $admin);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Siswa dipindahkan ke {$target->name}."]);

        return back();
    }

    /**
     * Schedule table rows grouped by module: default, override (batch or this
     * student) and effective unlock date, plus whether it is already open.
     *
     * @return array<int, array<string, mixed>>
     */
    private function scheduleRows(Course $course, Batch $batch, ?Enrollment $enrollment, ComputeLessonAccess $access): array
    {
        $modules = Module::where('course_id', $course->id)->ordered()->with(['lessons' => fn ($query) => $query->published()])->get();
        /** @var Collection<int, Lesson> $lessons */
        $lessons = $modules->flatMap(fn (Module $module) => $module->lessons)->values();

        $batchSchedule = $access->scheduleForBatch($batch, $lessons);
        $studentAccess = $enrollment ? $access->forEnrollment($enrollment, $lessons) : null;
        $studentOverrides = $enrollment
            ? LessonUnlockOverride::where('enrollment_id', $enrollment->id)->where('batch_id', $batch->id)->pluck('unlock_at', 'lesson_id')
            : collect();

        $now = now();

        return $modules->filter(fn (Module $module) => $module->lessons->isNotEmpty())->map(fn (Module $module) => [
            'id' => $module->id,
            'title' => $module->title,
            'lessons' => $module->lessons->map(function (Lesson $lesson) use ($batchSchedule, $studentAccess, $studentOverrides, $now) {
                $row = $batchSchedule[$lesson->id];
                $effective = $studentAccess ? ($studentAccess[$lesson->id]->unlockAt ?? $now) : $row['effective'];
                $override = $studentAccess ? ($studentOverrides[$lesson->id] ?? null) : $row['override'];

                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'type' => $lesson->type->value,
                    'is_preview' => $lesson->is_preview,
                    'day_number' => $lesson->dayNumber(),
                    'default_unlock_at' => $row['default']->toIso8601String(),
                    'batch_unlock_at' => $row['effective']->toIso8601String(),
                    'override_unlock_at' => $override ? CarbonImmutable::parse($override)->toIso8601String() : null,
                    'effective_unlock_at' => $effective->toIso8601String(),
                    'is_open' => $lesson->is_preview || $effective->lte($now),
                ];
            })->values()->all(),
        ])->values()->all();
    }

    private function applyOverride(Request $request, Course $course, Batch $batch, ?Enrollment $enrollment, Lesson $lesson, SetLessonUnlockOverride $set): RedirectResponse
    {
        Gate::authorize('manageClass', $course);
        $this->ensureBelongs($course, $batch, $enrollment, $lesson);

        $data = $request->validate(['unlock_at' => ['required', 'date']], [], ['unlock_at' => 'tanggal buka']);

        /** @var User $user */
        $user = $request->user();
        $set->set($lesson, $batch, $enrollment, CarbonImmutable::parse((string) $data['unlock_at'], config('app.timezone')), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Jadwal \"{$lesson->title}\" diperbarui."]);

        return back();
    }

    private function removeOverride(Course $course, Batch $batch, ?Enrollment $enrollment, Lesson $lesson, SetLessonUnlockOverride $set): RedirectResponse
    {
        Gate::authorize('manageClass', $course);
        $this->ensureBelongs($course, $batch, $enrollment, $lesson);

        $set->reset($lesson, $batch, $enrollment);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Jadwal \"{$lesson->title}\" kembali ke jadwal ".($enrollment ? 'batch' : 'default').'.']);

        return back();
    }

    /**
     * URL consistency: the batch, student and lesson must belong to the course (404 otherwise).
     */
    private function ensureBelongs(Course $course, Batch $batch, ?Enrollment $enrollment = null, ?Lesson $lesson = null): void
    {
        abort_unless(
            $course->drip_enabled
            && $batch->course_id === $course->id
            && ($enrollment === null || $enrollment->batch_id === $batch->id)
            && ($lesson === null || $lesson->course_id === $course->id),
            404,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function courseProps(Course $course): array
    {
        return ['id' => $course->id, 'title' => $course->title, 'slug' => $course->slug, 'drip_enabled' => $course->drip_enabled];
    }
}
