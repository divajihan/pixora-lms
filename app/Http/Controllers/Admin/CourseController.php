<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Courses\ChangeCourseStatus;
use App\Actions\Courses\ListAdminCourses;
use App\Actions\Courses\PublishCourse;
use App\Actions\Courses\SaveCourse;
use App\Enums\CourseInstructorRole;
use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseRequest;
use App\Http\Resources\Admin\AdminCourseResource;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin course management. Routes: `permission:courses.manage` + CoursePolicy.
 */
class CourseController extends Controller
{
    public function index(Request $request, ListAdminCourses $listCourses): Response
    {
        Gate::authorize('manage', Course::class);

        $search = Str::of((string) $request->query('q', ''))->squish()->limit(100, '')->toString();
        $status = CourseStatus::tryFrom((string) $request->query('status', ''));
        $categoryId = filter_var($request->query('kategori'), FILTER_VALIDATE_INT) ?: null;

        $courses = $listCourses->handle($search ?: null, $status, $categoryId)
            ->through(fn (Course $course) => AdminCourseResource::make($course)->resolve());

        return Inertia::render('admin/courses/index', [
            'courses' => $courses,
            'filters' => [
                'q' => $search ?: null,
                'status' => $status?->value,
                'kategori' => $categoryId,
            ],
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Course::class);

        return Inertia::render('admin/courses/create', [
            'categories' => $this->categories(),
        ]);
    }

    public function store(CourseRequest $request, SaveCourse $saveCourse): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $course = $saveCourse->handle(null, $request->validated(), $user, $request->file('thumbnail'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kursus dibuat sebagai draf. Lanjutkan dengan menyusun kurikulum.']);

        return to_route('admin.courses.edit', $course);
    }

    public function edit(Course $course, PublishCourse $publishCourse): Response
    {
        Gate::authorize('update', $course);

        $course->load(['category:id,name', 'instructors:users.id,users.name,users.email'])
            ->loadCount(['modules', 'lessons']);

        $assigned = $course->instructors->modelKeys();

        return Inertia::render('admin/courses/edit', [
            'course' => AdminCourseResource::make($course)->resolve(),
            'categories' => $this->categories(),
            'statusTargets' => array_map(fn (CourseStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ], ChangeCourseStatus::targetsFor($course->status)),
            'publishBlockers' => $publishCourse->blockers($course),
            'instructors' => $course->instructors->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRelationValue('assignment')?->role?->value,
            ])->values(),
            'availableInstructors' => User::role(Role::Instructor->value)
                ->where('is_active', true)
                ->whereKeyNot($assigned)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'instructorRoles' => array_map(fn (CourseInstructorRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ], CourseInstructorRole::cases()),
        ]);
    }

    public function update(CourseRequest $request, Course $course, SaveCourse $saveCourse): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $saveCourse->handle(
            $course,
            $request->validated(),
            $user,
            $request->file('thumbnail'),
            $request->boolean('remove_thumbnail'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan kursus disimpan.']);

        return to_route('admin.courses.edit', $course);
    }

    /**
     * Soft delete (orders/enrollments will keep referring to it later).
     */
    public function destroy(Course $course): RedirectResponse
    {
        Gate::authorize('delete', $course);

        $course->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kursus \"{$course->title}\" dihapus."]);

        return to_route('admin.courses.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categories(): array
    {
        return Category::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Category $category) => ['id' => $category->id, 'name' => $category->name])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return array_map(fn (CourseStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], CourseStatus::cases());
    }
}
