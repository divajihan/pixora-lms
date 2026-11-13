<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveSession>
 */
class LiveSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(3, 30))->setTime(19, 0);

        return [
            'lesson_id' => Lesson::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(1),
            'platform' => fake()->randomElement(['Zoom', 'Google Meet']),
            'meeting_url' => 'https://meet.example.test/'.fake()->lexify('???-????-???'),
            'notes' => 'Siapkan pertanyaan Anda sebelum sesi dimulai.',
        ];
    }
}
