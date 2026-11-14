<?php

use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:expire-pending', function () {
    $count = Order::where('status', OrderStatus::Pending)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->update(['status' => OrderStatus::Expired, 'updated_at' => now()]);

    $this->info("{$count} order kedaluwarsa.");
})->purpose('Mark pending orders past expires_at as expired');

Artisan::command('enrollments:expire-ended', function () {
    $count = Enrollment::where('status', EnrollmentStatus::Active)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->update(['status' => EnrollmentStatus::Expired, 'updated_at' => now()]);

    $this->info("{$count} enrollment kedaluwarsa.");
})->purpose('Mark enrollments past expires_at as expired');

// Requires the system cron: * * * * * php artisan schedule:run
Schedule::command('orders:expire-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('enrollments:expire-ended')->daily()->withoutOverlapping();
