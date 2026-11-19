<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Props of the "Sertifikat" card on the learning overview (/belajar/{course}):
 * requirements with met/not met, the claim date, whether the claim button is
 * enabled, and the active certificate (number, PDF status, links). Null when
 * the course issues no certificates and none exists.
 */
class BuildCertificateCard
{
    public function __construct(private readonly CheckCertificateEligibility $eligibility) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Enrollment $enrollment, User $user): ?array
    {
        $result = $this->eligibility->handle($enrollment);

        if (! $result->enabled && $result->certificate === null) {
            return null;
        }

        return [
            ...$result->toArray(),
            'default_name' => $user->name,
            'certificate' => $result->certificate ? self::certificate($result->certificate) : null,
        ];
    }

    /**
     * Owner-facing certificate summary (no file path).
     *
     * @return array<string, mixed>
     */
    public static function certificate(Certificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'number' => $certificate->certificate_number,
            'recipient_name' => $certificate->recipient_name,
            'course_title' => $certificate->course_title,
            'batch_name' => $certificate->batch_name,
            'issued_at' => $certificate->issued_at->toIso8601String(),
            'pdf_ready' => $certificate->isPdfReady(),
            'download_url' => route('certificates.download', $certificate),
            'verification_url' => $certificate->verificationUrl(),
            'verification_code' => $certificate->verification_code,
        ];
    }
}
