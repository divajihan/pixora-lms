<?php

namespace App\Policies;

use App\Actions\Learning\ComputeLessonAccess;
use App\Enums\Permission;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Lessons. Content managers (courses.manage) edit them; students see their
 * content only when ComputeLessonAccess says the lesson is open for them.
 */
class LessonPolicy
{
    public function __construct(private readonly ComputeLessonAccess $access) {}

    public function view(User $user, Lesson $lesson): bool
    {
        return (new CoursePolicy)->viewCurriculum($user, $lesson->course)->allowed();
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    /**
     * Student may see the lesson's protected content (video, summary, meeting link).
     */
    public function viewContent(User $user, Lesson $lesson): Response
    {
        $enrollment = $this->enrollment($user, $lesson);

        if ($enrollment === null) {
            return Response::denyAsNotFound();
        }

        return $this->access->canViewLesson($enrollment, $lesson)
            ? Response::allow()
            : Response::deny('Lesson ini belum terbuka untuk Anda.');
    }

    /**
     * Mark/unmark completion: same rule as viewing the content.
     */
    public function complete(User $user, Lesson $lesson): Response
    {
        return $this->viewContent($user, $lesson);
    }

    private function enrollment(User $user, Lesson $lesson): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $lesson->course_id)
            ->with(['course', 'batch'])
            ->first();
    }
}
