<?php

namespace App\Providers;

use App\Services\Payment\FakeGateway;
use App\Services\Payment\MidtransGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Video\VideoProvider;
use App\Services\Video\YouTubeProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap here to change video host (e.g. Bunny/Vimeo) without touching Actions.
        $this->app->singleton(VideoProvider::class, YouTubeProvider::class);

        $this->app->singleton(MidtransGateway::class, fn () => MidtransGateway::fromConfig());
        $this->app->singleton(FakeGateway::class, function () {
            // The fake signing key is public (in source): accepting fake notifications
            // in production would let anyone mark orders as paid.
            if ($this->app->isProduction()) {
                throw new RuntimeException('FakeGateway cannot be used in production. Set PAYMENT_GATEWAY=midtrans.');
            }

            return FakeGateway::fromConfig();
        });
        $this->app->singleton(PaymentGateway::class, fn () => match (config('pixora.payments.gateway')) {
            'midtrans' => $this->app->make(MidtransGateway::class),
            'fake' => $this->app->make(FakeGateway::class),
            default => throw new RuntimeException('Unknown PAYMENT_GATEWAY: use "midtrans" or "fake".'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Indonesian URLs for resource routes: /kursus/buat, /kursus/{id}/ubah.
        Route::resourceVerbs(['create' => 'buat', 'edit' => 'ubah']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
