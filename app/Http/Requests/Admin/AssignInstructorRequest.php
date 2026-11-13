<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourseInstructorRole;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignInstructorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course && (bool) $this->user()?->can('update', $course);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::enum(CourseInstructorRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['user_id' => 'instruktur', 'role' => 'peran'];
    }
}
