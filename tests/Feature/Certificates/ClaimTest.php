<?php

use App\Actions\Certificates\ClaimCertificate;
use App\Actions\Certificates\NextCertificateNumber;
use App\Enums\EnrollmentStatus;
use App\Jobs\GenerateCertificatePdf;
use App\Models\Certificate;
use App\Models\CertificateSequence;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('certificates');
    $this->s = certificateCourse();
});

function claim(array $s, string $name = 'Siti Aminah, S.Ds.'): TestResponse
{
    return actingAsStudent($s['student'])->post(route('learn.certificate.claim', $s['course']), ['recipient_name' => $name]);
}

test('an eligible student claims: snapshot, number, enrollment completed, PDF queued', function () {
    Queue::fake();
    completeLessons($this->s['enrollment'], $this->s['lessons']);

    claim($this->s, '  Siti   Aminah,  S.Ds. ')->assertRedirect(route('learn.course', $this->s['course']))->assertSessionHasNoErrors();

    $certificate = Certificate::sole();
    expect($certificate->recipient_name)->toBe('Siti Aminah, S.Ds.')
        ->and($certificate->course_title)->toBe('Desain Logo')
        ->and($certificate->certificate_number)->toBe('PXR-CERT-'.now()->year.'-000001')
        ->and($certificate->verification_code)->toMatch('/^[A-HJ-NP-Z2-9]{12}$/')
        ->and($certificate->active_enrollment_id)->toBe($this->s['enrollment']->id)
        ->and($certificate->issued_by)->toBeNull()
        ->and($this->s['enrollment']->fresh()->status)->toBe(EnrollmentStatus::Completed)
        ->and($this->s['enrollment']->fresh()->completed_at)->not->toBeNull();

    Queue::assertPushed(GenerateCertificatePdf::class, fn (GenerateCertificatePdf $job) => $job->certificate->is($certificate));
});

test('completed enrollments keep learning access', function () {
    completeLessons($this->s['enrollment'], $this->s['lessons']);
    claim($this->s)->assertSessionHasNoErrors();

    actingAsStudent($this->s['student'])->get(route('learn.course', $this->s['course']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificate.status', 'claimed')
            ->where('certificate.certificate.number', Certificate::sole()->certificate_number)
            ->where('certificate.certificate.pdf_ready', true)
            ->missing('certificate.certificate.pdf_path'));

    // The catalog does not offer to buy it again.
    expect($this->s['student']->enrollments()->accessible()->count())->toBe(1);
});

test('the server re-checks eligibility (UI is not trusted)', function () {
    completeLessons($this->s['enrollment'], [$this->s['lessons'][0]]);

    claim($this->s)->assertSessionHasErrors(['certificate' => 'Syarat belum terpenuhi: Selesaikan semua lesson (1 dari 2).']);
    expect(Certificate::count())->toBe(0)
        ->and($this->s['enrollment']->fresh()->status)->toBe(EnrollmentStatus::Active);
});

test('claiming twice returns the same certificate (idempotent)', function () {
    completeLessons($this->s['enrollment'], $this->s['lessons']);

    claim($this->s, 'Siti Aminah')->assertSessionHasNoErrors();
    claim($this->s, 'Nama Lain')->assertSessionHasNoErrors();
    $again = app(ClaimCertificate::class)->handle($this->s['enrollment'], 'Nama Ketiga');

    expect(Certificate::count())->toBe(1)
        ->and($again->wasRecentlyCreated)->toBeFalse()
        ->and($again->recipient_name)->toBe('Siti Aminah')
        ->and(CertificateSequence::sole()->last_number)->toBe(1);
});

test('the database allows one active certificate per enrollment (concurrent claims)', function () {
    completeLessons($this->s['enrollment'], $this->s['lessons']);
    claim($this->s)->assertSessionHasNoErrors();

    expect(fn () => Certificate::factory()->create(['enrollment_id' => $this->s['enrollment']->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('numbers are sequential per year and never reused', function () {
    $numbers = collect(range(1, 5))->map(fn () => DB::transaction(fn () => app(NextCertificateNumber::class)->handle(now())));

    expect($numbers->unique())->toHaveCount(5)
        ->and($numbers->last())->toBe('PXR-CERT-'.now()->year.'-000005');

    // Rolled-back claims give the number back; a new year starts at 1.
    try {
        DB::transaction(function () {
            app(NextCertificateNumber::class)->handle(now());
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::transaction(fn () => app(NextCertificateNumber::class)->handle(now())))->toBe('PXR-CERT-'.now()->year.'-000006')
        ->and(DB::transaction(fn () => app(NextCertificateNumber::class)->handle(now()->addYear())))->toBe('PXR-CERT-'.(now()->year + 1).'-000001');
});

test('certificate numbers are unique in the database', function () {
    $first = Certificate::factory()->create();

    expect(fn () => Certificate::factory()->create(['certificate_number' => $first->certificate_number]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the recipient name is a snapshot: profile changes do not alter it', function () {
    completeLessons($this->s['enrollment'], $this->s['lessons']);
    claim($this->s, 'Siti Aminah')->assertSessionHasNoErrors();

    $this->s['student']->update(['name' => 'Nama Baru']);
    $this->s['course']->update(['title' => 'Judul Baru']);

    $certificate = Certificate::sole();
    expect($certificate->recipient_name)->toBe('Siti Aminah')->and($certificate->course_title)->toBe('Desain Logo');

    $this->get(route('certificates.verify', $certificate->verification_code))
        ->assertInertia(fn (Assert $page) => $page->where('result.recipient_name', 'Siti Aminah')->where('result.course_title', 'Desain Logo'));
});

test('recipient name validation', function (string $name) {
    completeLessons($this->s['enrollment'], $this->s['lessons']);

    claim($this->s, $name)->assertSessionHasErrors('recipient_name');
    expect(Certificate::count())->toBe(0);
})->with([
    'empty' => [''],
    'spaces' => ['    '],
    'symbols only' => ['!!! --- ...'],
    'digits only' => ['12345'],
    'too long' => [str_repeat('a', 121)],
]);

test('students cannot claim for someone else\'s enrollment (no enrollment = 404)', function () {
    completeLessons($this->s['enrollment'], $this->s['lessons']);
    $other = User::factory()->create()->assignRole('student');

    actingAsStudent($other)->post(route('learn.certificate.claim', $this->s['course']), ['recipient_name' => 'Penyusup'])->assertNotFound();
    expect(Certificate::count())->toBe(0);
});

test('the claim endpoint is rate limited', function () {
    completeLessons($this->s['enrollment'], $this->s['lessons']);

    foreach (range(1, 10) as $i) {
        claim($this->s)->assertRedirect();
    }

    claim($this->s)->assertTooManyRequests();
});
