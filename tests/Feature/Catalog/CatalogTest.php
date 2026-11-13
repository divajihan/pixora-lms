<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function publishedCourse(array $attributes = []): Course
{
    $instructor = InstructorProfile::factory()->create()->user;

    return Course::factory()
        ->published()
        ->hasAttached($instructor, ['role' => 'lead'], 'instructors')
        ->create($attributes);
}

test('the catalog lists only published courses', function () {
    $visible = publishedCourse(['title' => 'Kursus Terbit']);
    Course::factory()->draft()->create(['title' => 'Kursus Draf']);
    Course::factory()->archived()->create(['title' => 'Kursus Arsip']);
    Course::factory()->scheduled()->create(['title' => 'Kursus Terjadwal']);
    publishedCourse(['title' => 'Kursus Dihapus'])->delete();

    $this->get(route('courses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/courses/index')
            ->has('courses.data', 1)
            ->where('courses.data.0.slug', $visible->slug)
            ->where('courses.total', 1)
            ->has('courses.data.0.instructors', 1)
            ->missing('courses.data.0.description'));
});

test('the catalog can be searched by title', function () {
    publishedCourse(['title' => 'Dasar Desain Grafis']);
    publishedCourse(['title' => 'Laravel untuk Pemula']);
    publishedCourse(['title' => 'Sesuatu', 'summary' => 'Membahas desain juga']);

    $this->get(route('courses.index', ['q' => 'desain']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('courses.data', 1)
            ->where('courses.data.0.title', 'Dasar Desain Grafis')
            ->where('filters.q', 'desain'));
});

test('LIKE wildcards in the search are treated literally', function () {
    publishedCourse(['title' => 'Diskon 50% Kursus']);
    publishedCourse(['title' => 'Kursus Biasa']);

    $this->get(route('courses.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('courses.data', 1));
});

test('the catalog can be filtered by category and price', function () {
    $design = Category::factory()->create(['slug' => 'desain']);
    $web = Category::factory()->create(['slug' => 'web']);

    publishedCourse(['category_id' => $design->id, 'title' => 'Desain Gratis', 'is_free' => true, 'price' => 0]);
    publishedCourse(['category_id' => $design->id, 'title' => 'Desain Berbayar', 'is_free' => false, 'price' => 150_000]);
    publishedCourse(['category_id' => $web->id, 'title' => 'Web Gratis', 'is_free' => true, 'price' => 0]);

    $this->get(route('courses.index', ['kategori' => 'desain']))
        ->assertInertia(fn (Assert $page) => $page->where('courses.total', 2));

    $this->get(route('courses.index', ['harga' => 'gratis']))
        ->assertInertia(fn (Assert $page) => $page->where('courses.total', 2));

    $this->get(route('courses.index', ['harga' => 'berbayar']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.total', 1)
            ->where('courses.data.0.price', 150_000)
            ->where('courses.data.0.is_free', false));

    $this->get(route('courses.index', ['kategori' => 'desain', 'harga' => 'gratis']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.total', 1)
            ->where('courses.data.0.title', 'Desain Gratis')
            ->where('filters', ['q' => null, 'kategori' => 'desain', 'harga' => 'gratis']));
});

test('only categories with published courses are offered as filters', function () {
    $used = Category::factory()->create(['name' => 'Dipakai']);
    Category::factory()->create(['name' => 'Kosong']);
    $draftOnly = Category::factory()->create(['name' => 'Hanya Draf']);

    publishedCourse(['category_id' => $used->id]);
    Course::factory()->draft()->create(['category_id' => $draftOnly->id]);

    $this->get(route('courses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Dipakai'));
});

test('unknown filter values are ignored instead of erroring', function () {
    publishedCourse();

    $this->get(route('courses.index', ['harga' => 'mahal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.harga', null)
            ->where('courses.total', 1));

    $this->get(route('courses.index', ['kategori' => 'tidak-ada']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('courses.total', 0));
});

test('the catalog is paginated and keeps the query string', function () {
    foreach (range(1, 11) as $i) {
        publishedCourse(['title' => 'Kursus Desain '.$i]);
    }

    $this->get(route('courses.index', ['q' => 'Desain']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('courses.data', 9)
            ->where('courses.last_page', 2)
            ->where('courses.next_page_url', fn (string $url) => str_contains($url, 'q=Desain') && str_contains($url, 'page=2')));

    $this->get(route('courses.index', ['q' => 'Desain', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('courses.data', 2));
});

test('the catalog query count does not grow with the number of courses', function () {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('courses.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    foreach (range(1, 2) as $i) {
        publishedCourse();
    }
    $few = $countQueries();

    foreach (range(1, 7) as $i) {
        publishedCourse();
    }
    $many = $countQueries();

    expect($many)->toBe($few);
});

test('the home page shows the latest published courses', function () {
    foreach (range(1, 7) as $i) {
        publishedCourse(['published_at' => now()->subDays($i)]);
    }
    Course::factory()->draft()->create(['title' => 'Rahasia']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/home')
            ->has('latestCourses', 6)
            ->where('latestCourses', fn ($courses) => collect($courses)->doesntContain('title', 'Rahasia')));
});

test('a published course detail page is public', function () {
    $course = publishedCourse(['title' => 'Kursus Publik', 'price' => 199_000, 'is_free' => false]);

    $this->get(route('courses.show', $course))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/courses/show')
            ->where('course.title', 'Kursus Publik')
            ->where('course.price', 199_000)
            ->has('course.instructors', 1)
            ->has('course.instructors.0.headline')
            ->where('meta.title', 'Kursus Publik'));
});

test('draft, archived, scheduled and deleted courses return 404', function (Closure $makeCourse) {
    $course = $makeCourse();

    $this->get(route('courses.show', $course->slug))->assertNotFound();

    // Not visible to signed-in users either (previews arrive in Phase 2).
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin)->get(route('courses.show', $course->slug))->assertNotFound();
})->with([
    'draft' => fn () => Course::factory()->draft()->create(),
    'archived' => fn () => Course::factory()->archived()->create(),
    'scheduled' => fn () => Course::factory()->scheduled()->create(),
    'soft-deleted' => fn () => tap(Course::factory()->published()->create())->delete(),
]);

test('pages render title and meta description on the server', function () {
    $course = publishedCourse(['title' => 'Fotografi Produk', 'summary' => 'Foto produk yang menjual.']);

    // Tags come from the Blade fallback, or from Inertia SSR when the dev server
    // is running; both must produce the same title and description.
    $title = fn (string $text) => '/<title[^>]*>'.preg_quote($text, '/').'<\/title>/';
    $description = fn (string $text) => '/<meta name="description" content="'.preg_quote($text, '/').'"[^>]*data-inertia="description"/';

    $html = $this->get(route('courses.show', $course))->assertOk()->getContent();
    expect($html)
        ->toMatch($title('Fotografi Produk - '.config('app.name')))
        ->toMatch($description('Foto produk yang menjual.'));

    expect($this->get(route('courses.index'))->getContent())
        ->toMatch($title('Katalog Kursus - '.config('app.name')));
});

test('the Blade fallback renders meta tags when SSR is unavailable', function () {
    config(['inertia.ssr.enabled' => false]);
    $course = publishedCourse(['title' => 'Tanpa SSR', 'summary' => 'Ringkasan tanpa SSR.']);

    $this->get(route('courses.show', $course))
        ->assertSee('<title>Tanpa SSR - '.config('app.name').'</title>', false)
        ->assertSee('<meta name="description" content="Ringkasan tanpa SSR." data-inertia="description">', false);
});
