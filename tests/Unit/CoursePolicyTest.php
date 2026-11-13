<?php

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Policies\CoursePolicy;

test('guests may view published courses only, others are denied as not found', function (CourseStatus $status, ?string $publishedAt, bool $allowed) {
    $course = new Course([
        'status' => $status,
        'published_at' => $publishedAt ? now()->modify($publishedAt) : null,
    ]);

    $response = (new CoursePolicy)->view(null, $course);

    expect($response->allowed())->toBe($allowed);

    if (! $allowed) {
        expect($response->status())->toBe(404);
    }
})->with([
    'published' => [CourseStatus::Published, '-1 day', true],
    'published without date' => [CourseStatus::Published, null, true],
    'scheduled' => [CourseStatus::Published, '+1 day', false],
    'draft' => [CourseStatus::Draft, null, false],
    'archived' => [CourseStatus::Archived, '-1 day', false],
]);

test('anyone may browse the catalog', function () {
    expect((new CoursePolicy)->viewAny(null))->toBeTrue();
});
