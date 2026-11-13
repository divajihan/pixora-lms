<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a course (admin). Authorization: route middleware
 * `permission:courses.manage` + CoursePolicy.
 */
class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course
            ? (bool) $this->user()?->can('update', $course)
            : (bool) $this->user()?->can('create', Course::class);
    }

    protected function prepareForValidation(): void
    {
        // multipart/form-data sends booleans as strings.
        foreach (['is_free', 'drip_enabled', 'certificate_enabled', 'remove_thumbnail'] as $field) {
            $this->merge([$field => filter_var($this->input($field, false), FILTER_VALIDATE_BOOLEAN)]);
        }

        $this->merge([
            'slug' => is_string($this->input('slug')) ? trim($this->input('slug')) : null,
            'access_duration_days' => $this->filled('access_duration_days') ? $this->input('access_duration_days') : null,
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('courses', 'slug')->ignore($course instanceof Course ? $course->id : null),
            ],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'is_free' => ['boolean'],
            'price' => ['exclude_if:is_free,true', 'required', 'integer', 'min:1000', 'max:100000000'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_thumbnail' => ['boolean'],
            'drip_enabled' => ['boolean'],
            'access_duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'certificate_enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'slug' => 'slug',
            'summary' => 'ringkasan',
            'description' => 'deskripsi',
            'category_id' => 'kategori',
            'price' => 'harga',
            'thumbnail' => 'gambar sampul',
            'access_duration_days' => 'durasi akses',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung (contoh: kursus-desain-dasar).',
            'price.required' => 'Harga wajib diisi untuk kursus berbayar.',
            'price.min' => 'Harga kursus berbayar minimal Rp 1.000.',
        ];
    }
}
