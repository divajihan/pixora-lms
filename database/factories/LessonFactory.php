<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: a published video lesson. The course is taken from the module.
 *
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * YouTube IFrame API sample video (used in Google's own docs).
     */
    public const SAMPLE_VIDEO_ID = 'M7lc1UVf-VE';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'module_id' => Module::factory(),
            'course_id' => fn (array $attributes) => Module::query()->whereKey($attributes['module_id'])->value('course_id'),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'type' => LessonType::Video,
            'video_provider' => 'youtube',
            'video_ref' => self::SAMPLE_VIDEO_ID,
            'duration_seconds' => fake()->numberBetween(180, 1200),
            'summary_html' => '<p>'.fake()->sentence(12).'</p>',
            'drip_day_offset' => 0,
            'position' => 0,
            'status' => LessonStatus::Published,
            'is_preview' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => LessonStatus::Published]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => LessonStatus::Draft]);
    }

    public function preview(): static
    {
        return $this->state(fn () => ['is_preview' => true]);
    }

    public function video(string $ref = self::SAMPLE_VIDEO_ID): static
    {
        return $this->state(fn () => [
            'type' => LessonType::Video,
            'video_provider' => 'youtube',
            'video_ref' => $ref,
        ]);
    }

    /**
     * A live lesson with its schedule (live_sessions row).
     */
    public function live(): static
    {
        return $this->state(fn () => [
            'type' => LessonType::Live,
            'video_provider' => null,
            'video_ref' => null,
            'duration_seconds' => 3600,
        ])->has(LiveSession::factory(), 'liveSession');
    }
}
