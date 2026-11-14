<?php

namespace App\Http\Requests\Admin;

use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Module;
use App\Rules\VideoUrl;
use App\Services\Video\VideoProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');
        $module = $this->route('module');

        return match (true) {
            $lesson instanceof Lesson => (bool) $this->user()?->can('update', $lesson),
            $module instanceof Module => (bool) $this->user()?->can('addLesson', $module),
            default => false,
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_preview' => filter_var($this->input('is_preview', false), FILTER_VALIDATE_BOOLEAN),
            'duration_seconds' => $this->filled('duration_seconds') ? $this->input('duration_seconds') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isVideo = $this->input('type') === LessonType::Video->value;
        $isLive = $this->input('type') === LessonType::Live->value;

        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::enum(LessonType::class)],
            'status' => ['required', Rule::enum(LessonStatus::class)],
            'video_url' => $isVideo
                ? ['required', 'string', 'max:500', new VideoUrl(app(VideoProvider::class))]
                : ['nullable', 'string', 'max:500'],
            'summary_html' => ['nullable', 'string', 'max:100000'],
            'drip_day_offset' => ['required', 'integer', 'min:0', 'max:3650'],
            'is_preview' => ['boolean'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'live_starts_at' => $isLive ? ['required', 'date'] : ['nullable'],
            'live_ends_at' => $isLive ? ['nullable', 'date', 'after:live_starts_at'] : ['nullable'],
            'live_platform' => ['nullable', 'string', 'max:50'],
            'live_meeting_url' => ['nullable', 'url:http,https', 'max:500'],
            'live_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul lesson',
            'type' => 'tipe',
            'video_url' => 'URL video',
            'summary_html' => 'ringkasan',
            'drip_day_offset' => 'hari buka',
            'duration_seconds' => 'durasi',
            'live_starts_at' => 'waktu mulai',
            'live_ends_at' => 'waktu selesai',
            'live_platform' => 'platform',
            'live_meeting_url' => 'tautan meeting',
            'live_notes' => 'catatan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video_url.required' => 'URL video wajib diisi untuk lesson bertipe video.',
            'live_starts_at.required' => 'Waktu mulai wajib diisi untuk kelas live.',
            'live_ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }
}
