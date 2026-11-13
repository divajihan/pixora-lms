<?php

use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\CourseInstructorController;
use App\Http\Controllers\Admin\CourseStatusController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Instructor\CourseController as InstructorCourseController;
use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\HomeController;
use Illuminate\Support\Facades\Route;

// Public pages (no login).
Route::get('/', HomeController::class)->name('home');
Route::get('kursus', [CourseController::class, 'index'])->name('courses.index');
Route::get('kursus/{course:slug}', [CourseController::class, 'show'])
    ->name('courses.show')
    ->can('view', 'course');

Route::middleware(['auth', 'verified'])->group(function () {
    // Fortify "home": redirects to the role-specific dashboard below.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Admin area. Staff share the dashboard; everything else is gated by permission
    // (staff have neither courses.manage nor users.manage → 403).
    Route::middleware('role:admin|staff')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');

        Route::middleware('permission:courses.manage')->group(function () {
            Route::resource('kursus', AdminCourseController::class)
                ->parameters(['kursus' => 'course'])
                ->names('courses')
                ->except('show');

            Route::patch('kursus/{course}/status', CourseStatusController::class)->name('courses.status');
            Route::post('kursus/{course}/instruktur', [CourseInstructorController::class, 'store'])->name('courses.instructors.store');
            Route::delete('kursus/{course}/instruktur/{user}', [CourseInstructorController::class, 'destroy'])->name('courses.instructors.destroy');

            Route::get('kursus/{course}/kurikulum', [CurriculumController::class, 'show'])->name('courses.curriculum');
            Route::put('kursus/{course}/kurikulum/urutan', [CurriculumController::class, 'reorder'])->name('courses.curriculum.reorder');

            Route::post('kursus/{course}/modul', [ModuleController::class, 'store'])->name('modules.store');
            Route::put('modul/{module}', [ModuleController::class, 'update'])->name('modules.update');
            Route::delete('modul/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');

            Route::post('modul/{module}/lesson', [LessonController::class, 'store'])->name('lessons.store');
            Route::put('lesson/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
            Route::delete('lesson/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('pengguna', [UserController::class, 'index'])->name('users.index');
            Route::post('pengguna', [UserController::class, 'store'])->name('users.store');
            Route::put('pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        });
    });

    Route::middleware('role:instructor')->prefix('instruktur')->name('instructor.')->group(function () {
        Route::inertia('/', 'instructor/dashboard')->name('dashboard');
        Route::get('kursus', [InstructorCourseController::class, 'index'])->name('courses.index');
        Route::get('kursus/{course}', [InstructorCourseController::class, 'show'])->name('courses.show');
    });

    Route::middleware('role:student')->prefix('siswa')->name('student.')->group(function () {
        Route::inertia('/', 'student/dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
