<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a published batch that starts in a week and runs 13 days.
 *
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory()->state(['drip_enabled' => true]),
            'name' => 'Batch '.fake()->unique()->numberBetween(1, 999),
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->addDays(12)->toDateString(),
            'registration_closes_on' => null,
            'capacity' => null,
            'is_published' => true,
        ];
    }

    /**
     * Starts on the given date (Y-m-d) and runs $days days (inclusive of start).
     */
    public function startingOn(string $date, int $days = 13): static
    {
        return $this->state(fn () => [
            'starts_on' => $date,
            'ends_on' => now()->parse($date)->addDays($days - 1)->toDateString(),
        ]);
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subDays(3)->toDateString(),
            'ends_on' => now()->addDays(9)->toDateString(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->addDays(10)->toDateString(),
            'ends_on' => now()->addDays(22)->toDateString(),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subDays(40)->toDateString(),
            'ends_on' => now()->subDays(28)->toDateString(),
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
