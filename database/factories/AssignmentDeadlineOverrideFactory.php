<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentDeadlineOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Set batch_id or enrollment_id when creating.
 *
 * @extends Factory<AssignmentDeadlineOverride>
 */
class AssignmentDeadlineOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'batch_id' => null,
            'enrollment_id' => null,
            'due_at' => now()->addWeek(),
        ];
    }
}
