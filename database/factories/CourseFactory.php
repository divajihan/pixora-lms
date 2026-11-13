<?php

namespace Database\Factories;

use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Default: a published, paid course.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(4), '.');

        return [
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'summary' => fake()->sentence(16),
            'description' => fake()->paragraph(4)."\n\n".fake()->paragraph(4),
            'thumbnail_path' => null,
            'price' => fake()->randomElement([99_000, 149_000, 199_000, 249_000, 349_000]),
            'is_free' => false,
            'status' => CourseStatus::Published,
            'drip_enabled' => false,
            'access_duration_days' => null,
            'certificate_enabled' => true,
            'published_at' => now()->subDays(fake()->numberBetween(1, 90)),
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Archived,
        ]);
    }

    /**
     * Published, but scheduled for a future date (not yet public).
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Published,
            'published_at' => now()->addWeek(),
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
            'is_free' => true,
        ]);
    }

    public function paid(int $price = 199_000): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
            'is_free' => false,
        ]);
    }
}
