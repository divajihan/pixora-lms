<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Fortify "home": redirects to the role-specific dashboard below.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Staff shares the admin area (permissions narrow what they can do inside it).
    Route::middleware('role:admin|staff')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');
    });

    Route::middleware('role:instructor')->prefix('instruktur')->name('instructor.')->group(function () {
        Route::inertia('/', 'instructor/dashboard')->name('dashboard');
    });

    Route::middleware('role:student')->prefix('siswa')->name('student.')->group(function () {
        Route::inertia('/', 'student/dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
