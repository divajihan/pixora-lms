<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Support\CertificateSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Texts printed on every certificate PDF (plain text; escaped in the view).
 */
class CertificateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::SettingsManage->value);
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (CertificateSettings::FIELDS as $field) {
            $values[$field] = trim((string) preg_replace('/\s+/u', ' ', (string) $this->input($field, '')));
        }

        $this->merge($values);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $text = fn (int $max) => ['required', 'string', "max:{$max}", 'regex:/\p{L}/u'];

        return [
            'institution_name' => $text(100),
            'title' => $text(60),
            'statement' => $text(120),
            'signer_name' => $text(80),
            'signer_title' => $text(80),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'institution_name' => 'nama lembaga',
            'title' => 'judul sertifikat',
            'statement' => 'kalimat pernyataan',
            'signer_name' => 'nama penanda tangan',
            'signer_title' => 'jabatan penanda tangan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['*.regex' => ':Attribute harus berisi huruf.'];
    }
}
