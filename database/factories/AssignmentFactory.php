<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a published final project (lesson_id null), due 3 days after the
 * reference date. Use forLesson() for a lesson assignment.
 *
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'lesson_id' => null,
            'title' => 'Tugas: '.fake()->sentence(3),
            'instructions_html' => '<p>Unggah hasil latihan Anda dalam bentuk gambar.</p>',
            'due_offset_days' => 3,
            'is_required' => false,
            'max_images' => 5,
            'status' => AssignmentStatus::Published,
        ];
    }

    public function forLesson(Lesson $lesson): static
    {
        return $this->state(fn () => ['course_id' => $lesson->course_id, 'lesson_id' => $lesson->id]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => AssignmentStatus::Draft]);
    }
}
