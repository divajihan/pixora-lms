<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Metadata only: the file itself is not written (tests that read files use
 * the real upload flow or put a file on the faked disk).
 *
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::random(40);

        return [
            'attachable_type' => 'submission',
            'attachable_id' => Submission::factory(),
            'disk' => (string) config('pixora.assignments.disk'),
            'path' => "submissions/test/{$name}.png",
            'thumbnail_path' => "submissions/test/{$name}_thumb.png",
            'original_name' => 'karya.png',
            'mime' => 'image/png',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ];
    }
}
