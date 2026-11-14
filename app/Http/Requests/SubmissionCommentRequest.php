<?php

namespace App\Http\Requests;

use App\Rules\AllowedImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Feedback comment: text and/or images (same image rules as submissions).
 * Authorisation (SubmissionPolicy::comment) and timing rules are checked in
 * the controller / PostSubmissionComment.
 */
class SubmissionCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'images' => ['nullable', 'array', 'max:'.(int) config('pixora.assignments.comment_max_images', 5)],
            'images.*' => [new AllowedImage],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['images.max' => 'Maksimal :max gambar per komentar.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => 'komentar', 'images' => 'gambar', 'images.*' => 'gambar'];
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        /** @var list<UploadedFile> $files */
        $files = array_values($this->file('images', []));

        return $files;
    }
}
