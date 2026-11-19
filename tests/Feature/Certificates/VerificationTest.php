<?php

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a valid certificate shows only what is printed on it', function () {
    $student = User::factory()->create(['name' => 'Akun Siti', 'email' => 'siti.rahasia@example.com', 'phone' => '081234567890']);
    $batch = Batch::factory()->create(['name' => 'Batch 44']);
    $enrollment = Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $batch->course_id, 'batch_id' => $batch->id]);
    $certificate = Certificate::factory()->create([
        'enrollment_id' => $enrollment->id, 'recipient_name' => 'Siti Aminah', 'course_title' => 'Desain Logo', 'batch_name' => 'Batch 44',
    ]);

    $response = $this->get(route('certificates.verify', $certificate->verification_code))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow"', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/certificates/verify')
            ->where('meta.noindex', true)
            ->where('result', [
                'status' => 'valid',
                'number' => $certificate->certificate_number,
                'recipient_name' => 'Siti Aminah',
                'course_title' => 'Desain Logo',
                'batch_name' => 'Batch 44',
                'issued_at' => $certificate->issued_at->toIso8601String(),
                'revoked_at' => null,
            ]));

    $content = $response->getContent();
    expect($content)->not->toContain('siti.rahasia@example.com')
        ->and($content)->not->toContain('081234567890')
        ->and($content)->not->toContain('Akun Siti');
});

test('codes are matched case-insensitively and with separators', function () {
    $certificate = Certificate::factory()->create(['verification_code' => 'ABCDEFGHJKLM']);

    $this->get(route('certificates.verify', 'abcd-efgh-jklm'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('result.number', $certificate->certificate_number)->where('code', 'ABCDEFGHJKLM'));

    $this->get(route('certificates.verify.form', ['kode' => ' abcd efgh jklm ']))
        ->assertRedirect(route('certificates.verify', 'ABCDEFGHJKLM'));
});

test('a revoked certificate is shown as revoked', function () {
    $certificate = Certificate::factory()->revoked()->create();

    $this->get(route('certificates.verify', $certificate->verification_code))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('result.status', 'revoked')->whereNot('result.revoked_at', null)->missing('result.revoke_reason'));
});

test('an unknown code shows a safe "not found" page', function (string $code) {
    Certificate::factory()->create();

    $this->get(route('certificates.verify', $code))->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page->component('public/certificates/verify')->where('result', ['status' => 'not_found']));
})->with(['ZZZZZZZZZZZZ', 'kode-salah', '1']);

test('the form page works and empty input stays on it', function () {
    $this->get(route('certificates.verify.form'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('result', null)->where('code', null));
    $this->get(route('certificates.verify.form', ['kode' => '<>!!']))->assertOk();
});

test('verification is throttled', function () {
    foreach (range(1, 30) as $i) {
        $this->get(route('certificates.verify', 'ZZZZZZZZZZZZ'))->assertNotFound();
    }

    $this->get(route('certificates.verify', 'ZZZZZZZZZZZZ'))->assertTooManyRequests();
});
