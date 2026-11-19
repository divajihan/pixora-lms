<?php

namespace App\Actions\Certificates;

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\RecipientName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Admin exception (certificates.manage): issues a certificate without the
 * lesson/assignment/date requirements, recording who (issued_by) and why
 * (issue_reason). Still refused for a revoked enrollment or when an active
 * certificate already exists.
 */
class IssueCertificateManually
{
    public function __construct(private readonly CreateCertificate $create) {}

    public function handle(Enrollment $enrollment, string $recipientName, string $reason, User $admin): Certificate
    {
        $certificate = DB::transaction(function () use ($enrollment, $recipientName, $reason, $admin): Certificate {
            /** @var Enrollment $locked */
            $locked = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === EnrollmentStatus::Revoked) {
                throw ValidationException::withMessages(['email' => 'Akses siswa ini ke kursus telah dicabut; sertifikat tidak dapat diterbitkan.']);
            }

            $existing = $locked->activeCertificate()->first();

            if ($existing !== null) {
                throw ValidationException::withMessages(['email' => "Siswa ini sudah memiliki sertifikat aktif ({$existing->certificate_number})."]);
            }

            return $this->create->handle($locked, RecipientName::normalise($recipientName), $admin, trim($reason));
        });

        Log::info('Certificate issued manually', ['certificate' => $certificate->certificate_number, 'admin_id' => $admin->id]);

        return $certificate;
    }
}
