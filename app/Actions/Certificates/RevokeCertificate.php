<?php

namespace App\Actions\Certificates;

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Admin revokes a certificate (reason required). The row stays (the
 * verification page shows "Dicabut"), active_enrollment_id is cleared so the
 * student can claim again — with the correct name and a NEW number — and a
 * completed enrollment goes back to active.
 */
class RevokeCertificate
{
    public function handle(Certificate $certificate, string $reason, User $admin): Certificate
    {
        return DB::transaction(function () use ($certificate, $reason, $admin): Certificate {
            /** @var Certificate $locked */
            $locked = Certificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();

            if ($locked->isRevoked()) {
                throw ValidationException::withMessages(['reason' => 'Sertifikat ini sudah dicabut.']);
            }

            $locked->forceFill([
                'active_enrollment_id' => null,
                'revoked_at' => now(),
                'revoked_by' => $admin->id,
                'revoke_reason' => trim($reason),
            ])->save();

            Enrollment::whereKey($locked->enrollment_id)
                ->where('status', EnrollmentStatus::Completed)
                ->update(['status' => EnrollmentStatus::Active, 'completed_at' => null, 'updated_at' => now()]);

            Log::info('Certificate revoked', ['certificate' => $locked->certificate_number, 'admin_id' => $admin->id]);

            return $locked;
        });
    }
}
