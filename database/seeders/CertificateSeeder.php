<?php

namespace Database\Seeders;

use App\Actions\Certificates\CheckCertificateEligibility;
use App\Actions\Certificates\ClaimCertificate;
use App\Actions\Certificates\IssueCertificateManually;
use App\Actions\Certificates\RevokeCertificate;
use App\Enums\Role;
use App\Jobs\GenerateCertificatePdf;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Certificates\CertificatePdfRenderer;
use Illuminate\Database\Seeder;

/**
 * Phase 6 demo data (development only, idempotent):
 * - finished batch of the first drip course: the first eligible student claims
 *   with a typo, admin revokes it, the student claims again (new number);
 * - another student of that batch gets a manual certificate (exception);
 * PDFs are generated synchronously so downloads work without a queue worker.
 */
class CertificateSeeder extends Seeder
{
    public function __construct(
        private readonly CheckCertificateEligibility $eligibility,
        private readonly ClaimCertificate $claim,
        private readonly RevokeCertificate $revoke,
        private readonly IssueCertificateManually $issue,
        private readonly CertificatePdfRenderer $renderer,
    ) {}

    public function run(): void
    {
        $admin = User::role(Role::Admin->value)->orderBy('id')->first();
        $course = Course::where('drip_enabled', true)->where('is_free', true)->published()->orderBy('id')->first();
        $batch = $course === null ? null : Batch::where('course_id', $course->id)->orderBy('starts_on')->first();

        if ($admin === null || $batch === null || Certificate::where('course_id', $batch->course_id)->exists()) {
            return;
        }

        $enrollments = Enrollment::where('batch_id', $batch->id)->with('user', 'course', 'batch')->orderBy('id')->get();

        if ($enrollments->isEmpty()) {
            return;
        }

        $results = $this->eligibility->many($enrollments);
        $eligible = $enrollments->first(fn (Enrollment $enrollment) => $results[$enrollment->id]->eligible());

        if ($eligible !== null) {
            $name = (string) $eligible->user?->name;
            $typo = $this->claim->handle($eligible, mb_substr($name, 0, -2).strrev(mb_substr($name, -2)));
            $this->revoke->handle($typo, 'Demo: nama salah ketik, siswa diminta klaim ulang.', $admin);
            $this->generate($this->claim->handle($eligible, $name));
        } else {
            $this->command?->warn('CertificateSeeder: no eligible enrollment in '.$batch->name.'; skipped the claim demo.');
        }

        $manual = $enrollments->first(fn (Enrollment $enrollment) => $enrollment->id !== $eligible?->id);

        if ($manual !== null) {
            $this->generate($this->issue->handle($manual, (string) $manual->user?->name, 'Demo: mengikuti kelas pengganti secara offline.', $admin));
        }
    }

    private function generate(Certificate $certificate): void
    {
        (new GenerateCertificatePdf($certificate))->handle($this->renderer);
    }
}
