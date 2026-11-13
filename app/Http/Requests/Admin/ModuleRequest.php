<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;

class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $module = $this->route('module');
        $course = $this->route('course');

        return match (true) {
            $module instanceof Module => (bool) $this->user()?->can('update', $module),
            $course instanceof Course => (bool) $this->user()?->can('update', $course),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['title' => 'judul modul'];
    }
}
