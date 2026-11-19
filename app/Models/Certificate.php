<?php

namespace App\Models;

use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An issued certificate. Created only by ClaimCertificate (student) or
 * IssueCertificateManually (admin); revoked by RevokeCertificate. The PDF is
 * rendered by the GenerateCertificatePdf job onto the private "certificates"
 * disk and served only by CertificateController (CertificatePolicy).
 *
 * @property int $id
 * @property int $enrollment_id
 * @property int|null $active_enrollment_id = enrollment_id while valid, null once revoked.
 * @property int $user_id
 * @property int $course_id
 * @property int|null $batch_id
 * @property string $certificate_number
 * @property string $verification_code
 * @property string $recipient_name Snapshot at issue time.
 * @property string $course_title Snapshot at issue time.
 * @property string|null $batch_name Snapshot at issue time.
 * @property Carbon $issued_at
 * @property int|null $issued_by Admin who issued it manually.
 * @property string|null $issue_reason
 * @property string|null $pdf_path
 * @property Carbon|null $pdf_generated_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property string|null $revoke_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'enrollment_id', 'active_enrollment_id', 'user_id', 'course_id', 'batch_id', 'certificate_number', 'verification_code',
    'recipient_name', 'course_title', 'batch_name', 'issued_at', 'issued_by', 'issue_reason', 'pdf_path',
    'pdf_generated_at', 'revoked_at', 'revoked_by', 'revoke_reason',
])]
#[Hidden(['pdf_path'])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Certificate>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isPdfReady(): bool
    {
        return $this->pdf_path !== null;
    }

    public function isManual(): bool
    {
        return $this->issued_by !== null;
    }

    public function verificationUrl(): string
    {
        return route('certificates.verify', $this->verification_code);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
