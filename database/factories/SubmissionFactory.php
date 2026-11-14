<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: submitted now, not reviewed, without images (add Attachment
 * factories for files).
 *
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'enrollment_id' => Enrollment::factory(),
            'body' => null,
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn () => ['status' => SubmissionStatus::Reviewed, 'reviewed_at' => now()]);
    }
}
