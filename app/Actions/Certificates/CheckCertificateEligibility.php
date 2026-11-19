<?php

namespace App\Actions\Certificates;

use App\Enums\AssignmentStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\LessonStatus;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Single source of truth for "can this enrollment claim a certificate?".
 * Used by the UI (learning overview, class roster) and re-checked by
 * ClaimCertificate inside its transaction — never trust the UI.
 *
 * Requirements:
 * 1. every published lesson (in a live module) completed — "x dari y";
 * 2. every published, is_required assignment (final project, or on a published
 *    lesson) submitted — on time or late;
 * 3. drip courses: the claim date has come — certificates_open_on of the
 *    batch, else ends_on + 1 day, at 00:00 (Asia/Jakarta). Non-drip: no date.
 *
 * Blockers: course without certificates, course draft/deleted, enrollment
 * revoked/expired, drip enrollment without a batch.
 *
 * many() evaluates enrollments of ONE course with a fixed number of queries
 * (class roster).
 */
class CheckCertificateEligibility
{
    public function handle(Enrollment $enrollment, ?CarbonImmutable $now = null): CertificateEligibility
    {
        return $this->many(new Collection([$enrollment]), $now)[$enrollment->id];
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments  Same course.
     * @return array<int, CertificateEligibility> keyed by enrollment id
     */
    public function many(Collection $enrollments, ?CarbonImmutable $now = null): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        if ($enrollments->pluck('course_id')->unique()->count() !== 1) {
            throw new InvalidArgumentException('CheckCertificateEligibility::many expects enrollments of one course.');
        }

        $now ??= CarbonImmutable::now();
        $enrollments->loadMissing(['course', 'batch']);
        /** @var Enrollment $first */
        $first = $enrollments->first();
        /** @var Course $course */
        $course = $first->course;
        $ids = $enrollments->pluck('id')->all();

        /** @var list<int> $lessonIds */
        $lessonIds = Lesson::query()
            ->where('course_id', $course->id)
            ->where('status', LessonStatus::Published)
            ->whereHas('module')
            ->pluck('id')
            ->all();

        $completed = $lessonIds === [] ? [] : LessonProgress::query()
            ->whereIn('enrollment_id', $ids)
            ->whereIn('lesson_id', $lessonIds)
            ->where('status', LessonProgressStatus::Completed)
            ->selectRaw('enrollment_id, count(*) as aggregate')
            ->groupBy('enrollment_id')
            ->pluck('aggregate', 'enrollment_id')
            ->all();

        /** @var list<int> $assignmentIds */
        $assignmentIds = Assignment::query()
            ->where('course_id', $course->id)
            ->where('status', AssignmentStatus::Published)
            ->where('is_required', true)
            ->where(fn (Builder $query) => $query->whereNull('lesson_id')->orWhereIn('lesson_id', $lessonIds))
            ->pluck('id')
            ->all();

        $submitted = $assignmentIds === [] ? [] : Submission::query()
            ->whereIn('enrollment_id', $ids)
            ->whereIn('assignment_id', $assignmentIds)
            ->selectRaw('enrollment_id, count(*) as aggregate')
            ->groupBy('enrollment_id')
            ->pluck('aggregate', 'enrollment_id')
            ->all();

        $certificates = Certificate::query()->whereIn('active_enrollment_id', $ids)->get()->keyBy('enrollment_id');

        $results = [];

        foreach ($enrollments as $enrollment) {
            $results[$enrollment->id] = $this->evaluate(
                $enrollment,
                $course,
                $now,
                (int) ($completed[$enrollment->id] ?? 0),
                count($lessonIds),
                (int) ($submitted[$enrollment->id] ?? 0),
                count($assignmentIds),
                $certificates->get($enrollment->id),
            );
        }

        return $results;
    }

    private function evaluate(
        Enrollment $enrollment,
        Course $course,
        CarbonImmutable $now,
        int $lessonsCompleted,
        int $lessonsTotal,
        int $assignmentsSubmitted,
        int $assignmentsTotal,
        ?Certificate $certificate,
    ): CertificateEligibility {
        $opensAt = $course->drip_enabled ? $enrollment->batch?->certificatesOpenAt() : null;

        $requirements = [[
            'key' => 'lessons',
            'label' => "Selesaikan semua lesson ({$lessonsCompleted} dari {$lessonsTotal})",
            'met' => $lessonsTotal > 0 && $lessonsCompleted >= $lessonsTotal,
        ]];

        if ($assignmentsTotal > 0) {
            $requirements[] = [
                'key' => 'assignments',
                'label' => "Kumpulkan semua tugas wajib ({$assignmentsSubmitted} dari {$assignmentsTotal})",
                'met' => $assignmentsSubmitted >= $assignmentsTotal,
            ];
        }

        if ($opensAt !== null) {
            $requirements[] = [
                'key' => 'date',
                'label' => 'Klaim dibuka '.$opensAt->translatedFormat('j F Y').' pukul 00.00 WIB',
                'met' => $now->greaterThanOrEqualTo($opensAt),
            ];
        }

        return new CertificateEligibility(
            enabled: $course->certificate_enabled,
            blocker: $this->blocker($enrollment, $course, $now),
            requirements: $requirements,
            lessonsCompleted: $lessonsCompleted,
            lessonsTotal: $lessonsTotal,
            assignmentsSubmitted: $assignmentsSubmitted,
            assignmentsTotal: $assignmentsTotal,
            opensAt: $opensAt,
            certificate: $certificate,
        );
    }

    private function blocker(Enrollment $enrollment, Course $course, CarbonImmutable $now): ?string
    {
        return match (true) {
            ! $course->certificate_enabled => 'Kursus ini tidak menerbitkan sertifikat.',
            $course->trashed() || $course->status === CourseStatus::Draft => 'Kursus ini sedang tidak tersedia.',
            $enrollment->status === EnrollmentStatus::Revoked => 'Akses Anda ke kursus ini telah dicabut.',
            $enrollment->status === EnrollmentStatus::Expired,
            $enrollment->expires_at !== null && $enrollment->expires_at->lessThanOrEqualTo($now) => 'Masa akses Anda ke kursus ini telah berakhir.',
            $course->drip_enabled && $enrollment->batch === null => 'Anda belum terdaftar di batch kursus ini.',
            default => null,
        };
    }
}
