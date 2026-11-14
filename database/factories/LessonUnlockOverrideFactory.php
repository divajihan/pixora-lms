<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Lesson;
use App\Models\LessonUnlockOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonUnlockOverride>
 */
class LessonUnlockOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'batch_id' => Batch::factory(),
            'enrollment_id' => null,
            'unlock_at' => now()->addDay(),
            'created_by' => null,
        ];
    }
}
