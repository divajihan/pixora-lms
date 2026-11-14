<?php

namespace App\Actions\Courses;

use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Validation\ValidationException;

/**
 * Status workflow: draft → published → archived. A published course may go
 * back to draft; an archived course may be restored to draft or re-published.
 * Publishing always goes through PublishCourse (publish rules).
 */
class ChangeCourseStatus
{
    /**
     * Allowed transitions: from => [to, ...].
     */
    private const TRANSITIONS = [
        'draft' => ['published'],
        'published' => ['draft', 'archived'],
        'archived' => ['draft', 'published'],
    ];

    public function __construct(private readonly PublishCourse $publishCourse) {}

    public function handle(Course $course, CourseStatus $target): Course
    {
        if (! in_array($target->value, self::TRANSITIONS[$course->status->value], true)) {
            throw ValidationException::withMessages([
                'status' => "Status tidak dapat diubah dari \"{$course->status->label()}\" ke \"{$target->label()}\".",
            ]);
        }

        if ($target === CourseStatus::Published) {
            return $this->publishCourse->handle($course);
        }

        $course->forceFill(['status' => $target])->save();

        return $course;
    }

    /**
     * @return list<CourseStatus>
     */
    public static function targetsFor(CourseStatus $status): array
    {
        return array_map(
            fn (string $value) => CourseStatus::from($value),
            self::TRANSITIONS[$status->value],
        );
    }
}
