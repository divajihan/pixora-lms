<?php

use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Jobs\GenerateCertificatePdf;
use App\Models\Certificate;
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

Artisan::command('certificates:regenerate-pdf {number? : Certificate number} {--all : Every active certificate}', function () {
    $number = $this->argument('number');

    $query = Certificate::query()
        ->when($number, fn ($query) => $query->where('certificate_number', $number))
        ->when(! $number && ! $this->option('all'), fn ($query) => $query->whereNull('pdf_path'))
        ->when(! $number, fn ($query) => $query->whereNull('revoked_at'));

    $count = 0;

    foreach ($query->lazyById() as $certificate) {
        GenerateCertificatePdf::dispatch($certificate);
        $count++;
    }

    $this->info("{$count} PDF sertifikat masuk antrean.");
})->purpose('Queue certificate PDFs: missing ones (default), one number, or --all active');

// Requires the system cron: * * * * * php artisan schedule:run
Schedule::command('orders:expire-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('enrollments:expire-ended')->daily()->withoutOverlapping();
