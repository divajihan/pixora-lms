<?php

use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\CourseInstructorController;
use App\Http\Controllers\Admin\CourseStatusController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Instructor\CourseController as InstructorCourseController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Student\CourseController as StudentCourseController;
use App\Http\Controllers\Student\OrderController as StudentOrderController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Public pages (no login).
Route::get('/', HomeController::class)->name('home');
Route::get('kursus', [CourseController::class, 'index'])->name('courses.index');
Route::get('kursus/{course:slug}', [CourseController::class, 'show'])
    ->name('courses.show')
    ->can('view', 'course');

// Payment gateway notifications: public, CSRF-exempt (bootstrap/app.php), signature-verified.
Route::post('webhooks/midtrans', [WebhookController::class, 'midtrans'])
    ->middleware('throttle:120,1')
    ->name('webhooks.midtrans');

// Checkout & free enrollment (students). Guests are sent to login and back.
Route::middleware(['auth', 'verified', 'role:student'])->group(function () {
    Route::get('kursus/{course:slug}/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('kursus/{course:slug}/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('checkout.store');
    Route::post('kursus/{course:slug}/ambil', [CheckoutController::class, 'enrollFree'])
        ->middleware('throttle:10,1')
        ->name('checkout.free');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Learning player: owner of an accessible enrollment (EnrollmentPolicy) — lesson
    // content only when ComputeLessonAccess opens it (LessonPolicy::viewContent).
    Route::prefix('belajar/{course:slug}')->name('learn.')->group(function () {
        Route::get('/', [LearnController::class, 'course'])->name('course');
        Route::get('lesson/{lesson:slug}', [LearnController::class, 'lesson'])->name('lesson')->scopeBindings();
        Route::post('lesson/{lesson:slug}/selesai', [LearnController::class, 'complete'])
            ->middleware('throttle:60,1')->name('complete')->scopeBindings();
        Route::delete('lesson/{lesson:slug}/selesai', [LearnController::class, 'uncomplete'])
            ->middleware('throttle:60,1')->name('uncomplete')->scopeBindings();
    });

    // Running a class (assigned instructors + admins; CoursePolicy::manageClass).
    Route::prefix('kelas/{course}')->name('classes.')->group(function () {
        Route::get('batch', [ClassController::class, 'batches'])->name('batches');
        Route::get('batch/{batch}', [ClassController::class, 'students'])->name('students');
        Route::get('batch/{batch}/jadwal', [ClassController::class, 'schedule'])->name('schedule');
        Route::post('batch/{batch}/jadwal/{lesson}', [ClassController::class, 'setOverride'])->name('schedule.set');
        Route::delete('batch/{batch}/jadwal/{lesson}', [ClassController::class, 'resetOverride'])->name('schedule.reset');
        Route::get('batch/{batch}/siswa/{enrollment}/jadwal', [ClassController::class, 'studentSchedule'])->name('student-schedule');
        Route::post('batch/{batch}/siswa/{enrollment}/jadwal/{lesson}', [ClassController::class, 'setOverride'])->name('student-schedule.set');
        Route::delete('batch/{batch}/siswa/{enrollment}/jadwal/{lesson}', [ClassController::class, 'resetOverride'])->name('student-schedule.reset');
        Route::post('batch/{batch}/siswa/{enrollment}/pindah', [ClassController::class, 'moveEnrollment'])->name('move');
    });

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

            Route::get('kursus/{course}/batch', [BatchController::class, 'index'])->name('batches.index');
            Route::post('kursus/{course}/batch', [BatchController::class, 'store'])->name('batches.store');
            Route::put('batch/{batch}', [BatchController::class, 'update'])->name('batches.update');
            Route::patch('batch/{batch}/publikasi', [BatchController::class, 'togglePublish'])->name('batches.publish');
            Route::delete('batch/{batch}', [BatchController::class, 'destroy'])->name('batches.destroy');
            Route::put('kursus/{course}/kurikulum/urutan', [CurriculumController::class, 'reorder'])->name('courses.curriculum.reorder');

            Route::post('kursus/{course}/modul', [ModuleController::class, 'store'])->name('modules.store');
            Route::put('modul/{module}', [ModuleController::class, 'update'])->name('modules.update');
            Route::delete('modul/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');

            Route::post('modul/{module}/lesson', [LessonController::class, 'store'])->name('lessons.store');
            Route::put('lesson/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
            Route::delete('lesson/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
        });

        Route::middleware('permission:orders.manage')->group(function () {
            Route::get('pesanan', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('pesanan/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::post('pesanan/{order}/cek-status', [AdminOrderController::class, 'reconcile'])
                ->middleware('throttle:10,1')
                ->name('orders.reconcile');
            // Admin-only (OrderPolicy): manual payment and refund, with a required note.
            Route::post('pesanan/{order}/tandai-lunas', [AdminOrderController::class, 'markPaid'])->name('orders.mark-paid');
            Route::post('pesanan/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');
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

        Route::get('kursus', [StudentCourseController::class, 'index'])->name('courses.index');
        // Phase 3 URL (used in sent emails) → the learning player.
        Route::redirect('kursus/{course}/belajar', '/belajar/{course}')->name('courses.learn');

        Route::get('pesanan', [StudentOrderController::class, 'index'])->name('orders.index');
        Route::get('pesanan/{order}', [StudentOrderController::class, 'show'])->name('orders.show');
        Route::get('pesanan/{order}/invoice', [StudentOrderController::class, 'invoice'])->name('orders.invoice');
        Route::post('pesanan/{order}/cek-status', [StudentOrderController::class, 'reconcile'])
            ->middleware('throttle:6,1')
            ->name('orders.reconcile');

        // PAYMENT_GATEWAY=fake only (the controller also checks); never registered in production.
        if (! app()->isProduction()) {
            Route::post('pesanan/{order}/simulasi', [StudentOrderController::class, 'simulate'])
                ->middleware('throttle:20,1')
                ->name('orders.simulate');
        }
    });
});

require __DIR__.'/settings.php';
