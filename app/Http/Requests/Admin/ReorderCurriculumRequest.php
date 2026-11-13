<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Full curriculum order: modules: [{id, lessons: [lessonId, ...]}, ...].
 */
class ReorderCurriculumRequest extends FormRequest
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
            'modules' => ['present', 'array', 'max:200'],
            'modules.*.id' => ['required', 'integer', 'distinct'],
            'modules.*.lessons' => ['present', 'array', 'max:500'],
            'modules.*.lessons.*' => ['integer'],
        ];
    }
}
