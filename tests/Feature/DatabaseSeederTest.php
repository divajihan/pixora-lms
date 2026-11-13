<?php

use App\Enums\CourseStatus;
use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentSeeder;

test('database seeder creates one account per role and the demo catalog', function () {
    config(['pixora.seed.admin_email' => 'boss@pixora.test', 'pixora.seed.admin_password' => 'rahasia-lokal']);

    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'boss@pixora.test')->firstOrFail();
    expect($admin->hasRole(Role::Admin->value))->toBeTrue()
        ->and(Hash::check('rahasia-lokal', $admin->password))->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue();

    foreach (Role::cases() as $role) {
        expect(User::role($role->value)->count())->toBeGreaterThanOrEqual(1);
    }

    expect(Category::count())->toBeBetween(4, 5)
        ->and(Course::count())->toBe(12)
        ->and(Course::where('is_free', true)->count())->toBeGreaterThan(0)
        ->and(Course::where('is_free', false)->count())->toBeGreaterThan(0)
        ->and(Course::where('status', CourseStatus::Draft)->count())->toBeGreaterThan(0)
        ->and(Course::where('status', CourseStatus::Archived)->count())->toBeGreaterThan(0)
        ->and(Course::doesntHave('instructors')->count())->toBe(0)
        ->and(Course::where('is_free', true)->where('price', '>', 0)->count())->toBe(0);
});

test('an empty SEED_ADMIN_PASSWORD falls back instead of creating a blank password', function () {
    putenv('SEED_ADMIN_PASSWORD=');
    $config = require config_path('pixora.php');
    putenv('SEED_ADMIN_PASSWORD');

    expect($config['seed']['admin_password'])->toBe('password');
});

test('seeding twice does not duplicate data', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Course::count())->toBe(12)->and(Category::count())->toBe(5);
});

test('development data is never seeded in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0)
        ->and(Course::count())->toBe(0)
        ->and(Spatie\Permission\Models\Role::count())->toBe(count(Role::cases()));

    expect(fn () => app(DevelopmentSeeder::class)->run())
        ->toThrow(RuntimeException::class);
});

test('every published course gets a seeded curriculum', function () {
    $this->seed(DatabaseSeeder::class);

    $courses = Course::published()->with(['modules.lessons.liveSession'])->get();

    expect($courses)->not->toBeEmpty();

    foreach ($courses as $course) {
        expect($course->modules->count())->toBeBetween(2, 4);

        foreach ($course->modules as $module) {
            expect($module->lessons->count())->toBeBetween(3, 6);
        }

        $lessons = $course->modules->flatMap->lessons;
        expect($lessons->where('status', LessonStatus::Published)->count())->toBeGreaterThan(0)
            ->and($lessons->where('is_preview', true)->count())->toBeGreaterThan(0);

        foreach ($lessons->where('type', LessonType::Live) as $live) {
            expect($live->liveSession)->not->toBeNull()->and($live->video_ref)->toBeNull();
        }
    }

    $all = Lesson::all();
    expect($all->where('status', LessonStatus::Draft)->count())->toBeGreaterThan(0)
        ->and($all->where('type', LessonType::Live)->count())->toBeGreaterThan(0)
        ->and($all->where('type', LessonType::Video)->count())->toBeGreaterThan(0);
});
