<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Support\RecipientName;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student claims the certificate of their enrollment (ownership is checked by
 * CertificatePolicy::claim before this runs).
 *
 * One transaction with the enrollment row locked: an existing active
 * certificate is returned as is (idempotent: double click, two tabs), else
 * eligibility is re-checked on the server and the certificate created.
 * certificates.active_enrollment_id is unique, so even without the lock
 * (concurrent first claims) only one row can exist; the loser returns it.
 *
 * $certificate->wasRecentlyCreated tells a new claim from a repeated one.
 */
class ClaimCertificate
{
    public function __construct(
        private readonly CheckCertificateEligibility $eligibility,
        private readonly CreateCertificate $create,
    ) {}

    public function handle(Enrollment $enrollment, string $recipientName): Certificate
    {
        $name = RecipientName::normalise($recipientName);

        try {
            return DB::transaction(function () use ($enrollment, $name): Certificate {
                /** @var Enrollment $locked */
                $locked = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

                $existing = $locked->activeCertificate()->first();

                if ($existing !== null) {
                    return $existing;
                }

                $result = $this->eligibility->handle($locked);

                if (! $result->eligible()) {
                    throw ValidationException::withMessages(['certificate' => (string) $result->reason()]);
                }

                return $this->create->handle($locked, $name);
            });
        } catch (UniqueConstraintViolationException) {
            return Certificate::where('active_enrollment_id', $enrollment->id)->firstOrFail();
        }
    }
}
