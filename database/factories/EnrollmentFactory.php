<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: active lifetime enrollment.
 *
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'order_id' => null,
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now()->subDays(3),
            'expires_at' => null,
            'progress_percent' => 0,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => EnrollmentStatus::Expired, 'expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => EnrollmentStatus::Revoked]);
    }

    /**
     * Still marked active but past its end date (the daily command expires it).
     */
    public function lapsed(): static
    {
        return $this->state(fn () => ['status' => EnrollmentStatus::Active, 'expires_at' => now()->subHour()]);
    }
}
