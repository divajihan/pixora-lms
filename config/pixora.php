<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Development seed accounts
    |--------------------------------------------------------------------------
    |
    | Used only by Database\Seeders\DevelopmentSeeder, which never runs in
    | production. The defaults are for local development only.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | gateway: "midtrans" (Snap) or "fake" (local development and tests; no
    | keys needed, payments are simulated). Pending orders expire after
    | order_expiry_hours (also sent to Midtrans as the Snap expiry).
    |
    */

    'payments' => [
        'gateway' => env('PAYMENT_GATEWAY', 'fake'),
        'order_expiry_hours' => (int) env('ORDER_EXPIRY_HOURS', 24),
        // Signing key for FakeGateway notifications (not a secret; fake mode only).
        'fake_signing_key' => 'pixora-fake-gateway',
    ],

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL') ?: 'admin@pixora.test',
        // Empty values fall back to the defaults (an empty password is never used).
        'admin_password' => env('SEED_ADMIN_PASSWORD') ?: 'password',
    ],

];
