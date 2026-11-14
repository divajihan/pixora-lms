<?php

namespace Database\Factories;

use App\Enums\CommentAuthorRole;
use App\Models\Submission;
use App\Models\SubmissionComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionComment>
 */
class SubmissionCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'user_id' => User::factory(),
            'author_role' => CommentAuthorRole::Instructor,
            'body' => fake()->sentence(),
        ];
    }
}
