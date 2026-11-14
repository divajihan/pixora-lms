<?php

namespace App\Http\Requests\Admin;

use App\Models\Batch;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

class BatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');
        $course = $batch instanceof Batch ? $batch->course : $this->route('course');

        return $course instanceof Course && (bool) $this->user()?->can('update', $course);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_published' => filter_var($this->input('is_published', false), FILTER_VALIDATE_BOOLEAN),
            'registration_closes_on' => $this->filled('registration_closes_on') ? $this->input('registration_closes_on') : null,
            'capacity' => $this->filled('capacity') ? $this->input('capacity') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'registration_closes_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:ends_on'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_published' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama batch',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'registration_closes_on' => 'tanggal tutup pendaftaran',
            'capacity' => 'kuota',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_on.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            'registration_closes_on.before_or_equal' => 'Pendaftaran harus ditutup paling lambat pada tanggal selesai batch.',
        ];
    }
}
