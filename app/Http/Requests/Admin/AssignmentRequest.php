<?php

namespace App\Http\Requests\Admin;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/edit an assignment (admin, courses.manage). lesson_id is only read on
 * create (null = final project).
 */
class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        $course = $this->route('course');

        return match (true) {
            $assignment instanceof Assignment => (bool) $this->user()?->can('update', $assignment),
            $course instanceof Course => (bool) $this->user()?->can('create', Assignment::class),
            default => false,
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_required' => filter_var($this->input('is_required', false), FILTER_VALIDATE_BOOLEAN),
            'lesson_id' => $this->filled('lesson_id') ? $this->input('lesson_id') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lesson_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'instructions_html' => ['nullable', 'string', 'max:20000'],
            'due_offset_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_images' => ['required', 'integer', 'min:1', 'max:'.(int) config('pixora.assignments.max_images', 5)],
            'is_required' => ['boolean'],
            'status' => ['required', Rule::enum(AssignmentStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul tugas',
            'instructions_html' => 'instruksi',
            'due_offset_days' => 'tenggat (hari)',
            'max_images' => 'jumlah gambar maksimal',
            'status' => 'status',
        ];
    }
}
