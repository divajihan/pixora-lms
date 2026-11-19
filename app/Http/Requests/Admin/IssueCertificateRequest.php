<?php

namespace App\Http\Requests\Admin;

use App\Models\Certificate;
use App\Support\RecipientName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Manual issue (exception): course + student email identify the enrollment.
 */
class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage', Certificate::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipient_name' => RecipientName::normalise((string) $this->input('recipient_name', '')),
            'email' => trim((string) $this->input('email', '')),
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'email' => ['required', 'email', 'max:255'],
            'recipient_name' => RecipientName::rules(),
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'course_id' => 'kursus',
            'email' => 'email siswa',
            'recipient_name' => 'nama penerima',
            'reason' => 'alasan',
        ];
    }
}
