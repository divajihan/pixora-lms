<?php

namespace App\Http\Resources\Admin;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Certificate row for the admin/staff list (no file path).
 *
 * @mixin Certificate
 */
class AdminCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->certificate_number,
            'verification_code' => $this->verification_code,
            'verification_url' => $this->verificationUrl(),
            'recipient_name' => $this->recipient_name,
            'course_title' => $this->course_title,
            'batch_name' => $this->batch_name,
            'student' => $this->user ? ['name' => $this->user->name, 'email' => $this->user->email] : null,
            'issued_at' => $this->issued_at->toIso8601String(),
            'is_manual' => $this->isManual(),
            'issued_by' => $this->issuer?->name,
            'issue_reason' => $this->issue_reason,
            'status' => $this->isRevoked() ? 'revoked' : 'active',
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_by' => $this->revoker?->name,
            'revoke_reason' => $this->revoke_reason,
            'pdf_ready' => $this->isPdfReady(),
            'download_url' => route('certificates.download', $this->resource),
        ];
    }
}
