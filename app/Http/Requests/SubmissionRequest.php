<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Rules\AllowedImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Student submission (create or update). Images are checked by content
 * (AllowedImage); counts against the assignment's limit; business rules
 * (deadline, review) are in SubmitAssignment / UpdateSubmission.
 * Authorisation happens in the controller (enrollment + policy).
 */
class SubmissionRequest extends FormRequest
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
        $assignment = $this->route('assignment');
        $limit = $assignment instanceof Assignment ? $assignment->imageLimit() : (int) config('pixora.assignments.max_images', 5);
        $creating = $this->routeIs('learn.assignments.submit');

        return [
            'images' => [$creating ? 'required' : 'nullable', 'array', $creating ? 'min:1' : 'min:0', 'max:'.$limit],
            'images.*' => [new AllowedImage],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['integer'],
            'body' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Unggah minimal 1 gambar.',
            'images.min' => 'Unggah minimal 1 gambar.',
            'images.max' => 'Maksimal :max gambar per pengumpulan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['images' => 'gambar', 'images.*' => 'gambar', 'body' => 'catatan'];
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
