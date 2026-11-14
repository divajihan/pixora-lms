<?php

namespace App\Actions\Courses;

use App\Enums\CourseInstructorRole;
use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds an instructor to a course (or changes their role on it), and removes one.
 * Only users with the instructor role can be assigned. A published course must
 * keep at least one lead instructor.
 */
class AssignInstructor
{
    public function handle(Course $course, User $user, CourseInstructorRole $role): void
    {
        if (! $user->hasRole(Role::Instructor->value)) {
            throw ValidationException::withMessages([
                'user_id' => 'Hanya pengguna dengan peran instruktur yang dapat ditugaskan.',
            ]);
        }

        DB::transaction(function () use ($course, $user, $role): void {
            if ($role === CourseInstructorRole::Assistant) {
                $this->ensureAnotherLead($course, $user);
            }

            $course->instructors()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
        });
    }

    public function remove(Course $course, User $user): void
    {
        DB::transaction(function () use ($course, $user): void {
            $this->ensureAnotherLead($course, $user);

            $course->instructors()->detach($user->id);
        });
    }

    /**
     * Blocks removing/demoting the last lead instructor of a published course.
     */
    private function ensureAnotherLead(Course $course, User $user): void
    {
        if ($course->status !== CourseStatus::Published) {
            return;
        }

        $otherLeads = $course->instructors()
            ->wherePivot('role', CourseInstructorRole::Lead->value)
            ->whereKeyNot($user->id)
            ->lockForUpdate()
            ->count();

        $isLead = $course->instructors()
            ->wherePivot('role', CourseInstructorRole::Lead->value)
            ->whereKey($user->id)
            ->exists();

        if ($isLead && $otherLeads === 0) {
            throw ValidationException::withMessages([
                'user_id' => 'Kursus yang terbit harus memiliki minimal 1 instruktur utama. Tambahkan instruktur utama lain terlebih dahulu.',
            ]);
        }
    }
}
