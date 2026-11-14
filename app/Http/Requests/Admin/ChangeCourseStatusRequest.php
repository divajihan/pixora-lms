<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeCourseStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(CourseStatus::class)],
        ];
    }

    public function target(): CourseStatus
    {
        return CourseStatus::from((string) $this->validated('status'));
    }
}
