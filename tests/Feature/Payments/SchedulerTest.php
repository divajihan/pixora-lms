<?php

use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('orders:expire-pending expires only pending orders past expires_at', function () {
    $overdue = Order::factory()->expiredPending()->create();
    $fresh = Order::factory()->create(['expires_at' => now()->addHour()]);
    $paid = Order::factory()->paid()->create(['expires_at' => now()->subDay()]);

    $this->artisan('orders:expire-pending')->expectsOutputToContain('1 order kedaluwarsa')->assertSuccessful();

    expect($overdue->refresh()->status)->toBe(OrderStatus::Expired)
        ->and($fresh->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($paid->refresh()->status)->toBe(OrderStatus::Paid);
});

test('enrollments:expire-ended expires only active enrollments past expires_at', function () {
    $lapsed = Enrollment::factory()->lapsed()->create();
    $lifetime = Enrollment::factory()->create(['expires_at' => null]);
    $future = Enrollment::factory()->create(['expires_at' => now()->addMonth()]);
    $revoked = Enrollment::factory()->revoked()->create(['expires_at' => now()->subDay()]);

    $this->artisan('enrollments:expire-ended')->assertSuccessful();

    expect($lapsed->refresh()->status)->toBe(EnrollmentStatus::Expired)
        ->and($lifetime->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($future->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($revoked->refresh()->status)->toBe(EnrollmentStatus::Revoked);
});

test('the commands are scheduled every five minutes and daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event) => [trim(str($event->command)->after('artisan')->replace(["'", '"'], '')) => $event->expression]);

    expect($events['orders:expire-pending'] ?? null)->toBe('*/5 * * * *')
        ->and($events['enrollments:expire-ended'] ?? null)->toBe('0 0 * * *');
});
