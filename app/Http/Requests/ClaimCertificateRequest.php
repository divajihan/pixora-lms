<?php

namespace App\Http\Requests;

use App\Support\RecipientName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Claim: the recipient name printed on the certificate (prefilled from the
 * profile in the dialog; cannot be changed once issued). Ownership and
 * eligibility: CertificatePolicy::claim + ClaimCertificate.
 */
class ClaimCertificateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['recipient_name' => RecipientName::normalise((string) $this->input('recipient_name', ''))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['recipient_name' => RecipientName::rules()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['recipient_name' => 'nama penerima'];
    }
}
