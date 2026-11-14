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

    /*
    |--------------------------------------------------------------------------
    | Learning (drip per batch)
    |--------------------------------------------------------------------------
    |
    | A lesson with drip_day_offset N opens on batch.starts_on + N days at
    | unlock_time (HH:MM, app timezone Asia/Jakarta). Offset 0 = "Hari ke-1".
    |
    */

    'learning' => [
        'unlock_time' => env('LESSON_UNLOCK_TIME', '00:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assignments (image submissions + feedback)
    |--------------------------------------------------------------------------
    |
    | Deadline = end of day (due_time, app timezone) of reference date +
    | due_offset_days. Images are re-encoded on the server (EXIF removed,
    | longest side <= max_dimension) and stored on a PRIVATE disk; they are
    | only served through AttachmentController (policy checked).
    |
    | PHP must accept the request: upload_max_filesize >= max_image_kb and
    | post_max_size >= max_images x max_image_kb (+ form data). See README.
    |
    */

    'assignments' => [
        'due_time' => env('ASSIGNMENT_DUE_TIME', '23:59'),
        'disk' => env('ASSIGNMENT_DISK', 'assignments'),
        'max_images' => (int) env('ASSIGNMENT_MAX_IMAGES', 5),
        'max_image_kb' => (int) env('ASSIGNMENT_MAX_IMAGE_KB', 10240),
        'comment_max_images' => (int) env('ASSIGNMENT_COMMENT_MAX_IMAGES', 5),
        'max_dimension' => 3000,
        'thumbnail_dimension' => 480,
        // Refuse decoding huge images (memory): width x height.
        'max_pixels' => 40_000_000,
        'jpeg_quality' => 85,
        'webp_quality' => 85,
    ],

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
