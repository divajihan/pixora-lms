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

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL') ?: 'admin@pixora.test',
        // Empty values fall back to the defaults (an empty password is never used).
        'admin_password' => env('SEED_ADMIN_PASSWORD') ?: 'password',
    ],

];
