<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Function-style Pest tests in Feature/ run on the Laravel TestCase with a
| fresh database (roles & permissions are seeded, see Tests\TestCase).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests boot the framework (helpers, casts) but use no database.
pest()->extend(TestCase::class)->in('Unit');

require_once __DIR__.'/Helpers.php';
