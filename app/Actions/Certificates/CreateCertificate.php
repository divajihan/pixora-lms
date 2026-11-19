<?php

namespace App\Actions\Certificates;

use App\Enums\EnrollmentStatus;
use App\Jobs\GenerateCertificatePdf;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Shared core of ClaimCertificate and IssueCertificateManually. The caller
 * runs it inside a transaction with the enrollment row locked and has checked
 * that no active certificate exists.
 *
 * Snapshots recipient name, course title and batch name, takes a number from
 * the yearly sequence, marks the enrollment completed and queues the PDF after
 * commit.
 */
class CreateCertificate
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 12;

    public function __construct(private readonly NextCertificateNumber $numbers) {}

    public function handle(Enrollment $enrollment, string $recipientName, ?User $issuer = null, ?string $reason = null): Certificate
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('CreateCertificate must run inside a transaction.');
        }

        $enrollment->loadMissing(['course', 'batch']);
        $issuedAt = now();

        $certificate = Certificate::create([
            'enrollment_id' => $enrollment->id,
            'active_enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'batch_id' => $enrollment->batch_id,
            'certificate_number' => $this->numbers->handle($issuedAt),
            'verification_code' => $this->verificationCode(),
            'recipient_name' => $recipientName,
            'course_title' => (string) $enrollment->course?->title,
            'batch_name' => $enrollment->batch?->name,
            'issued_at' => $issuedAt,
            'issued_by' => $issuer?->id,
            'issue_reason' => $reason,
        ]);

        if ($enrollment->status === EnrollmentStatus::Active) {
            $enrollment->forceFill(['status' => EnrollmentStatus::Completed, 'completed_at' => $issuedAt])->save();
        }

        GenerateCertificatePdf::dispatch($certificate)->afterCommit();

        return $certificate;
    }

    /**
     * Random, unambiguous (no 0/O/1/I), 12 characters ≈ 60 bits: not guessable.
     */
    private function verificationCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Certificate::where('verification_code', $code)->exists());

        return $code;
    }

    /**
     * Normalises user input ("ab12-cd34 ef56" → "AB12CD34EF56"); null when it
     * cannot be a code (lookup is skipped).
     */
    public static function normaliseCode(string $input): ?string
    {
        $code = Str::upper((string) preg_replace('/[\s\-]+/', '', $input));

        return preg_match('/^['.self::CODE_ALPHABET.']{'.self::CODE_LENGTH.'}$/', $code) === 1 ? $code : null;
    }
}
