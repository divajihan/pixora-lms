<?php

use App\Actions\Certificates\CertificateEligibility;
use App\Actions\Certificates\CheckCertificateEligibility;
use App\Enums\EnrollmentStatus;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('certificates');
});

function eligibility(Enrollment $enrollment, ?string $at = null): CertificateEligibility
{
    return app(CheckCertificateEligibility::class)->handle($enrollment->fresh(), $at ? CarbonImmutable::parse($at, 'Asia/Jakarta') : null);
}

test('non-drip: all lessons completed is enough (no date requirement)', function () {
    $s = certificateCourse();

    $result = eligibility($s['enrollment']);
    expect($result->eligible())->toBeFalse()
        ->and($result->requirements)->toHaveCount(1)
        ->and($result->requirements[0]['label'])->toBe('Selesaikan semua lesson (0 dari 2)')
        ->and($result->reason())->toBe('Syarat belum terpenuhi: Selesaikan semua lesson (0 dari 2).');

    completeLessons($s['enrollment'], [$s['lessons'][0]]);
    expect(eligibility($s['enrollment'])->requirements[0]['label'])->toBe('Selesaikan semua lesson (1 dari 2)')
        ->and(eligibility($s['enrollment'])->eligible())->toBeFalse();

    completeLessons($s['enrollment'], $s['lessons']);
    expect(eligibility($s['enrollment'])->eligible())->toBeTrue()
        ->and(eligibility($s['enrollment'])->status())->toBe('claimable');
});

test('draft lessons and lessons of deleted modules do not count', function () {
    $s = certificateCourse();
    completeLessons($s['enrollment'], $s['lessons']);
    Lesson::factory()->for($s['lessons'][0]->module)->draft()->create(['course_id' => $s['course']->id]);

    expect(eligibility($s['enrollment'])->eligible())->toBeTrue();
});

test('required published assignments must be submitted; optional and draft ones do not count', function () {
    $s = certificateCourse();
    completeLessons($s['enrollment'], $s['lessons']);

    Assignment::factory()->forLesson($s['lessons'][0])->create(['is_required' => false]);
    $final = Assignment::factory()->create(['course_id' => $s['course']->id, 'is_required' => true, 'status' => 'draft']);
    expect(eligibility($s['enrollment'])->eligible())->toBeTrue();

    $required = Assignment::factory()->forLesson($s['lessons'][1])->create(['is_required' => true]);
    $final->update(['status' => 'published']);

    $result = eligibility($s['enrollment']);
    expect($result->eligible())->toBeFalse()
        ->and($result->requirements[1])->toBe(['key' => 'assignments', 'label' => 'Kumpulkan semua tugas wajib (0 dari 2)', 'met' => false]);

    Submission::factory()->create(['assignment_id' => $required->id, 'enrollment_id' => $s['enrollment']->id]);
    expect(eligibility($s['enrollment'])->requirements[1]['label'])->toBe('Kumpulkan semua tugas wajib (1 dari 2)');

    // Late submissions count too.
    Submission::factory()->create(['assignment_id' => $final->id, 'enrollment_id' => $s['enrollment']->id, 'submitted_at' => now()->addMonth()]);
    expect(eligibility($s['enrollment'])->eligible())->toBeTrue();
});

test('drip: claim opens at 00:00 the day after ends_on', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 08:00', 'Asia/Jakarta'));
    $s = dripSetup('2026-11-01'); // ends 2026-11-13
    completeLessons($s['enrollment'], [...$s['lessons'], $s['preview']]);

    $before = eligibility($s['enrollment'], '2026-11-13 23:59:59');
    expect($before->eligible())->toBeFalse()
        ->and($before->requirements[1]['key'])->toBe('date')
        ->and($before->requirements[1]['label'])->toBe('Klaim dibuka 14 November 2026 pukul 00.00 WIB')
        ->and($before->opensAt?->toIso8601String())->toBe('2026-11-14T00:00:00+07:00');

    expect(eligibility($s['enrollment'], '2026-11-14 00:00:00')->eligible())->toBeTrue();
});

test('drip: certificates_open_on overrides the default date (earlier or later)', function () {
    $s = dripSetup('2026-11-01');
    completeLessons($s['enrollment'], [...$s['lessons'], $s['preview']]);

    $s['batch']->update(['certificates_open_on' => '2026-11-10']);
    expect(eligibility($s['enrollment'], '2026-11-09 23:59')->eligible())->toBeFalse()
        ->and(eligibility($s['enrollment'], '2026-11-10 00:00')->eligible())->toBeTrue();

    $s['batch']->update(['certificates_open_on' => '2026-11-20']);
    expect(eligibility($s['enrollment'], '2026-11-19 12:00')->eligible())->toBeFalse()
        ->and(eligibility($s['enrollment'], '2026-11-20 00:00')->eligible())->toBeTrue();
});

test('blockers: certificate disabled, revoked or expired enrollment, draft course', function (Closure $break, string $message) {
    $s = certificateCourse();
    completeLessons($s['enrollment'], $s['lessons']);
    $break($s);

    $result = eligibility($s['enrollment']);
    expect($result->eligible())->toBeFalse()
        ->and($result->blocker)->toBe($message)
        ->and($result->reason())->toBe($message);
})->with([
    'certificate_enabled=false' => [fn (array $s) => $s['course']->update(['certificate_enabled' => false]), 'Kursus ini tidak menerbitkan sertifikat.'],
    'revoked' => [fn (array $s) => $s['enrollment']->update(['status' => EnrollmentStatus::Revoked]), 'Akses Anda ke kursus ini telah dicabut.'],
    'expired' => [fn (array $s) => $s['enrollment']->update(['status' => EnrollmentStatus::Expired]), 'Masa akses Anda ke kursus ini telah berakhir.'],
    'lapsed (still active, past expires_at)' => [fn (array $s) => $s['enrollment']->update(['expires_at' => now()->subMinute()]), 'Masa akses Anda ke kursus ini telah berakhir.'],
    'draft course' => [fn (array $s) => $s['course']->update(['status' => 'draft']), 'Kursus ini sedang tidak tersedia.'],
]);

test('an issued certificate stays valid when a new lesson is published later', function () {
    $s = certificateCourse();
    completeLessons($s['enrollment'], $s['lessons']);
    actingAsStudent($s['student'])->post(route('learn.certificate.claim', $s['course']), ['recipient_name' => 'Siti Aminah'])->assertSessionHasNoErrors();

    Lesson::factory()->for($s['lessons'][0]->module)->published()->create(['course_id' => $s['course']->id]);

    $result = eligibility($s['enrollment']);
    expect($result->status())->toBe('claimed')
        ->and($result->certificate?->isRevoked())->toBeFalse()
        ->and(Certificate::sole()->revoked_at)->toBeNull();

    $this->get(route('certificates.verify', Certificate::sole()->verification_code))->assertOk()
        ->assertInertia(fn ($page) => $page->where('result.status', 'valid'));
});
