<?php

use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Certificates\CertificatePdfRenderer;
use App\Support\CertificateSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    Storage::fake('certificates');
    $this->s = certificateCourse();
    completeLessons($this->s['enrollment'], $this->s['lessons']);
});

function claimAs(array $s, string $name): Certificate
{
    actingAsStudent($s['student'])->post(route('learn.certificate.claim', $s['course']), ['recipient_name' => $name])->assertSessionHasNoErrors();

    return Certificate::latest('id')->firstOrFail();
}

test('revoke (reason required), then the student claims again with a new number', function () {
    $first = claimAs($this->s, 'Siti Aminh');

    actingAsRole(Role::Admin);
    $this->post(route('admin.certificates.revoke', $first), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('admin.certificates.revoke', $first), ['reason' => 'Nama salah ketik.'])->assertSessionHasNoErrors();

    $first->refresh();
    expect($first->isRevoked())->toBeTrue()
        ->and($first->active_enrollment_id)->toBeNull()
        ->and($first->revoke_reason)->toBe('Nama salah ketik.')
        ->and($this->s['enrollment']->fresh()->status)->toBe(EnrollmentStatus::Active);

    $this->post(route('admin.certificates.revoke', $first), ['reason' => 'Lagi.'])->assertSessionHasErrors(['reason' => 'Sertifikat ini sudah dicabut.']);

    $this->get(route('certificates.verify', $first->verification_code))
        ->assertInertia(fn (Assert $page) => $page->where('result.status', 'revoked'));

    $second = claimAs($this->s, 'Siti Aminah');
    expect($second->id)->not->toBe($first->id)
        ->and($second->certificate_number)->not->toBe($first->certificate_number)
        ->and($second->certificate_number)->toEndWith('-000002')
        ->and($second->verification_code)->not->toBe($first->verification_code)
        ->and($second->recipient_name)->toBe('Siti Aminah')
        ->and(Certificate::active()->count())->toBe(1);

    // "Sertifikat saya" lists only the active one.
    actingAsStudent($this->s['student'])->get(route('student.certificates.index'))
        ->assertInertia(fn (Assert $page) => $page->has('certificates', 1)->where('certificates.0.number', $second->certificate_number));
});

test('manual issue skips the requirements and records who and why', function () {
    $s = certificateCourse(['title' => 'Kursus Lain']);
    $admin = actingAsRole(Role::Admin);

    $payload = ['course_id' => $s['course']->id, 'email' => $s['student']->email, 'recipient_name' => 'Siti Aminah', 'reason' => 'Ikut kelas offline pengganti.'];

    $this->post(route('admin.certificates.store'), [...$payload, 'reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('admin.certificates.store'), [...$payload, 'email' => 'tidak@ada.test'])->assertSessionHasErrors(['email' => 'Siswa dengan email ini tidak terdaftar di kursus tersebut.']);
    $this->post(route('admin.certificates.store'), $payload)->assertSessionHasNoErrors();

    $certificate = Certificate::where('course_id', $s['course']->id)->sole();
    expect($certificate->issued_by)->toBe($admin->id)
        ->and($certificate->issue_reason)->toBe('Ikut kelas offline pengganti.')
        ->and($certificate->pdf_path)->not->toBeNull()
        ->and($s['enrollment']->fresh()->status)->toBe(EnrollmentStatus::Completed);

    $this->post(route('admin.certificates.store'), $payload)->assertSessionHasErrors('email');

    $s['enrollment']->update(['status' => EnrollmentStatus::Revoked]);
    $certificate->forceFill(['revoked_at' => now(), 'active_enrollment_id' => null])->save();
    $this->post(route('admin.certificates.store'), $payload)->assertSessionHasErrors(['email' => 'Akses siswa ini ke kursus telah dicabut; sertifikat tidak dapat diterbitkan.']);
});

test('staff can list and download but not revoke, issue, regenerate or change settings', function () {
    $certificate = claimAs($this->s, 'Siti Aminah');
    actingAsRole(Role::Staff);

    $this->get(route('admin.certificates.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/certificates/index')->where('can.manage', false)->where('can.settings', false)->has('certificates.data', 1));
    $this->get(route('certificates.download', $certificate))->assertOk();

    $this->post(route('admin.certificates.revoke', $certificate), ['reason' => 'Coba cabut.'])->assertForbidden();
    $this->post(route('admin.certificates.store'), ['course_id' => $this->s['course']->id, 'email' => $this->s['student']->email, 'recipient_name' => 'X', 'reason' => 'Coba terbit.'])->assertForbidden();
    $this->post(route('admin.certificates.regenerate', $certificate))->assertForbidden();
    $this->get(route('admin.certificate-settings.edit'))->assertForbidden();

    expect($certificate->fresh()->isRevoked())->toBeFalse();
});

test('instructors and students have no admin certificate pages', function (Role $role) {
    actingAsRole($role);

    $this->get(route('admin.certificates.index'))->assertForbidden();
    $this->get(route('admin.certificate-settings.edit'))->assertForbidden();
})->with([Role::Instructor, Role::Student]);

test('admin list: search and filters', function () {
    claimAs($this->s, 'Siti Aminah');
    $other = Certificate::factory()->revoked()->create(['recipient_name' => 'Budi Santoso', 'course_title' => 'Ilustrasi']);
    actingAsRole(Role::Admin);

    $this->get(route('admin.certificates.index', ['q' => 'budi']))
        ->assertInertia(fn (Assert $page) => $page->has('certificates.data', 1)->where('certificates.data.0.recipient_name', 'Budi Santoso'));
    $this->get(route('admin.certificates.index', ['q' => $other->certificate_number]))
        ->assertInertia(fn (Assert $page) => $page->has('certificates.data', 1));
    $this->get(route('admin.certificates.index', ['status' => 'dicabut']))
        ->assertInertia(fn (Assert $page) => $page->has('certificates.data', 1)->where('certificates.data.0.status', 'revoked'));
    $this->get(route('admin.certificates.index', ['status' => 'aktif', 'kursus' => $this->s['course']->id]))
        ->assertInertia(fn (Assert $page) => $page->has('certificates.data', 1)->where('certificates.data.0.recipient_name', 'Siti Aminah')->missing('certificates.data.0.pdf_path'));
    $this->get(route('admin.certificates.index', ['q' => '100%_']))
        ->assertInertia(fn (Assert $page) => $page->has('certificates.data', 0));
});

test('admin list has no N+1 queries', function () {
    actingAsRole(Role::Admin);
    $make = fn (int $n) => Certificate::factory()->count($n)->revoked()->create(['issued_by' => User::factory(), 'revoked_by' => User::factory()]);
    $make(2);

    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.certificates.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    $count(); // warm up (permission cache)
    $few = $count();
    $make(8);

    expect($count())->toBe($few);
});

test('class roster: certificate status per student without N+1 (instructor, read-only)', function () {
    $s = dripSetup('2026-10-01'); // finished batch: claim date passed
    $instructor = instructorUser();
    $s['course']->instructors()->attach($instructor, ['role' => 'lead']);

    completeLessons($s['enrollment'], [...$s['lessons'], $s['preview']]);
    $claimable = $s['enrollment'];
    $notYet = Enrollment::factory()->create(['course_id' => $s['course']->id, 'batch_id' => $s['batch']->id, 'progress_percent' => 50]);
    $claimed = Enrollment::factory()->create(['course_id' => $s['course']->id, 'batch_id' => $s['batch']->id, 'status' => EnrollmentStatus::Completed, 'progress_percent' => 100]);
    Certificate::factory()->create(['enrollment_id' => $claimed->id]);

    $this->actingAs($instructor);
    $url = route('classes.students', [$s['course'], $s['batch']]);

    $statuses = fn () => collect($this->get($url)->assertOk()->inertiaProps('students.data'))->pluck('certificate.status', 'id');
    expect($statuses()->all())->toBe([$claimed->id => 'claimed', $notYet->id => 'not_eligible', $claimable->id => 'claimable']);

    $count = function () use ($url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        return count(DB::getQueryLog());
    };

    $count(); // warm up (permission cache)
    $few = $count();
    Enrollment::factory()->count(6)->create(['course_id' => $s['course']->id, 'batch_id' => $s['batch']->id]);
    expect($count())->toBe($few);
});

test('certificate settings: admin edits them, validated, used by the PDF', function () {
    actingAsRole(Role::Admin);

    $this->get(route('admin.certificate-settings.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('settings.title', 'Sertifikat Kelulusan'));

    $valid = [
        'institution_name' => 'Akademi Pixora', 'title' => 'Sertifikat Penyelesaian', 'statement' => 'telah lulus kursus',
        'signer_name' => 'Dewi Lestari', 'signer_title' => 'Kepala Akademik',
    ];

    $this->put(route('admin.certificate-settings.update'), [...$valid, 'title' => '', 'signer_name' => '!!!'])
        ->assertSessionHasErrors(['title', 'signer_name']);
    $this->put(route('admin.certificate-settings.update'), $valid)->assertSessionHasNoErrors();

    expect(app(CertificateSettings::class)->all())->toBe($valid);

    $certificate = claimAs($this->s, 'Siti Aminah');
    $html = view(CertificatePdfRenderer::VIEW, app(CertificatePdfRenderer::class)->data($certificate))->render();
    expect($html)->toContain('Akademi Pixora')->toContain('Sertifikat Penyelesaian')->toContain('Dewi Lestari')->toContain('Kepala Akademik');
});

test('batch form: certificates_open_on is optional and not before starts_on', function () {
    $s = dripSetup('2026-12-01');
    actingAsRole(Role::Admin);
    $payload = ['name' => 'Batch 1', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-13', 'is_published' => true];

    $this->put(route('admin.batches.update', $s['batch']), [...$payload, 'certificates_open_on' => '2026-11-30'])
        ->assertSessionHasErrors(['certificates_open_on' => 'Klaim sertifikat paling cepat dibuka pada tanggal mulai batch.']);
    $this->put(route('admin.batches.update', $s['batch']), [...$payload, 'certificates_open_on' => '2026-12-20'])->assertSessionHasNoErrors();
    expect($s['batch']->fresh()->certificates_open_on?->toDateString())->toBe('2026-12-20');

    $this->put(route('admin.batches.update', $s['batch']), [...$payload, 'certificates_open_on' => ''])->assertSessionHasNoErrors();
    expect($s['batch']->fresh()->certificates_open_on)->toBeNull();
});

test('role seeder: certificates.manage for admin, certificates.view for staff', function () {
    expect(RoleModel::findByName(Role::Admin->value)->hasPermissionTo(Permission::CertificatesManage->value))->toBeTrue()
        ->and(RoleModel::findByName(Role::Staff->value)->hasPermissionTo(Permission::CertificatesView->value))->toBeTrue()
        ->and(RoleModel::findByName(Role::Staff->value)->hasPermissionTo(Permission::CertificatesManage->value))->toBeFalse()
        ->and(RoleModel::findByName(Role::Instructor->value)->hasPermissionTo(Permission::CertificatesView->value))->toBeFalse();
});
