<?php

use App\Enums\CourseInstructorRole;
use App\Enums\Role;
use App\Jobs\GenerateCertificatePdf;
use App\Models\Certificate;
use App\Models\User;
use App\Services\Certificates\CertificatePdfRenderer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('certificates');
    $this->s = certificateCourse();
    completeLessons($this->s['enrollment'], $this->s['lessons']);
});

function claimedCertificate(array $s, string $name = 'Siti Aminah'): Certificate
{
    actingAsStudent($s['student'])->post(route('learn.certificate.claim', $s['course']), ['recipient_name' => $name])->assertSessionHasNoErrors();

    return Certificate::sole();
}

test('the job renders a PDF onto the private disk under a random name', function () {
    $certificate = claimedCertificate($this->s);

    expect($certificate->pdf_path)->toStartWith('certificates/'.now()->year.'/')
        ->and(basename((string) $certificate->pdf_path))->toMatch('/^[A-Za-z0-9]{40}\.pdf$/')
        ->and($certificate->pdf_generated_at)->not->toBeNull();

    Storage::disk('certificates')->assertExists((string) $certificate->pdf_path);
    expect(Storage::disk('certificates')->get((string) $certificate->pdf_path))->toStartWith('%PDF-');
});

test('the job retries 3 times with backoff and leaves no path when rendering fails', function () {
    Queue::fake();
    $certificate = claimedCertificate($this->s);
    $job = new GenerateCertificatePdf($certificate);

    expect($job->tries)->toBe(3)->and($job->backoff())->toBe([10, 60]);

    $failing = Mockery::mock(CertificatePdfRenderer::class);
    $failing->shouldReceive('render')->once()->andThrow(new RuntimeException('Dompdf crashed'));

    expect(fn () => $job->handle($failing))->toThrow(RuntimeException::class);
    expect($certificate->fresh()->pdf_path)->toBeNull();

    // The retry succeeds.
    $job->handle(app(CertificatePdfRenderer::class));
    expect($certificate->fresh()->pdf_path)->not->toBeNull();
    Storage::disk('certificates')->assertExists((string) $certificate->fresh()->pdf_path);
});

test('regenerating replaces the file and removes the old one', function () {
    $certificate = claimedCertificate($this->s);
    $old = (string) $certificate->pdf_path;

    actingAsRole(Role::Admin);
    $this->post(route('admin.certificates.regenerate', $certificate))->assertRedirect();

    $new = (string) $certificate->fresh()->pdf_path;
    expect($new)->not->toBe($old);
    Storage::disk('certificates')->assertMissing($old);
    Storage::disk('certificates')->assertExists($new);
});

test('artisan certificates:regenerate-pdf queues missing PDFs', function () {
    Queue::fake();
    claimedCertificate($this->s);

    $this->artisan('certificates:regenerate-pdf')->expectsOutput('1 PDF sertifikat masuk antrean.')->assertSuccessful();
    Queue::assertPushed(GenerateCertificatePdf::class, 2); // claim + command
});

test('long names and HTML in the name are escaped and shrink in the template', function () {
    $long = 'Raden Ayu Kusumawardhani Prasetyaningrum <b onmouseover=alert(1)>Siregar</b> & Putri';
    expect(mb_strlen($long))->toBeGreaterThan(60);

    $certificate = claimedCertificate($this->s, $long);
    $renderer = app(CertificatePdfRenderer::class);
    $html = view(CertificatePdfRenderer::VIEW, $renderer->data($certificate))->render();

    expect($html)->toContain('Raden Ayu Kusumawardhani Prasetyaningrum &lt;b onmouseover=alert(1)&gt;Siregar&lt;/b&gt; &amp; Putri')
        ->and($html)->not->toContain('<b onmouseover')
        ->and($renderer->data($certificate)['nameSize'])->toBe(17)
        ->and($renderer->render($certificate))->toStartWith('%PDF-');
});

test('downloads go through the policy per role', function () {
    $certificate = claimedCertificate($this->s);
    $url = route('certificates.download', $certificate);

    // Owner.
    actingAsStudent($this->s['student'])->get($url)->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertDownload('Sertifikat-'.$certificate->certificate_number.'.pdf');

    // Another student, an unrelated instructor: 404.
    $this->actingAs(User::factory()->create()->assignRole('student'))->get($url)->assertNotFound();
    $this->actingAs(instructorUser())->get($url)->assertNotFound();

    // Instructor of the course, staff, admin.
    $this->actingAs($this->s['course']->instructors()->firstOrFail())->get($url)->assertOk();
    actingAsRole(Role::Staff);
    $this->get($url)->assertOk();
    actingAsRole(Role::Admin);
    $this->get($url)->assertOk();

    // Guests are sent to login.
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
});

test('an assistant instructor of the course may download too', function () {
    $certificate = claimedCertificate($this->s);
    $assistant = instructorUser();
    $this->s['course']->instructors()->attach($assistant, ['role' => CourseInstructorRole::Assistant->value]);

    $this->actingAs($assistant)->get(route('certificates.download', $certificate))->assertOk();
});

test('owners cannot download a revoked certificate; staff still can', function () {
    $certificate = claimedCertificate($this->s);
    $certificate->forceFill(['revoked_at' => now(), 'active_enrollment_id' => null])->save();

    actingAsStudent($this->s['student'])->get(route('certificates.download', $certificate))->assertNotFound();
    actingAsRole(Role::Staff);
    $this->get(route('certificates.download', $certificate))->assertOk();
});

test('a PDF still being generated answers with a friendly message', function () {
    Queue::fake();
    $certificate = claimedCertificate($this->s);

    actingAsStudent($this->s['student'])->from(route('learn.course', $this->s['course']))
        ->get(route('certificates.download', $certificate))
        ->assertRedirect(route('learn.course', $this->s['course']))
        ->assertInertiaFlash('toast.message', 'PDF sertifikat sedang diproses. Coba lagi dalam beberapa saat.');
});

test('the PDF file is not reachable from a public path', function () {
    $certificate = claimedCertificate($this->s);

    expect(config('filesystems.disks.certificates.serve'))->toBeFalse()
        ->and(config('filesystems.disks.certificates.root'))->toContain('private');
    expect($this->get('/storage/'.$certificate->pdf_path)->status())->toBeIn([403, 404])
        ->and($this->get('/storage/certificates/'.basename((string) $certificate->pdf_path))->status())->toBeIn([403, 404]);
});
