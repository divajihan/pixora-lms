<?php

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;

beforeEach(function () {
    Storage::fake('public');
});

function ownImageUrl(): string
{
    return '/storage/content-images/'.str_repeat('a', 40).'.png';
}

test('admins upload an editor image: re-encoded, random name, relative URL', function () {
    actingAsRole(Role::Admin);

    $response = $this->postJson(route('admin.content-images.store'), [
        'image' => TestImages::pngUpload('contoh.png', 2400, 1200, withMetadata: true),
    ])->assertCreated();

    $url = $response->json('url');
    expect($url)->toMatch('#^/storage/content-images/[A-Za-z0-9]{40}\.png$#')
        ->and($response->json('width'))->toBe(1600)
        ->and($response->json('height'))->toBe(800);

    $path = substr($url, strlen('/storage/'));
    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('public')->get($path))->not->toContain(TestImages::GPS_MARKER);

    // The URL survives sanitising.
    expect(app(HtmlSanitizer::class)->clean('<p>Contoh:</p><img src="'.$url.'" alt="contoh">'))
        ->toBe('<p>Contoh:</p><img src="'.$url.'" alt="contoh" />');
});

test('non-images and fake images are refused', function (Closure $file) {
    actingAsRole(Role::Admin);

    $this->postJson(route('admin.content-images.store'), ['image' => $file()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'svg' => [fn () => UploadedFile::fake()->createWithContent('a.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'pdf renamed' => [fn () => UploadedFile::fake()->createWithContent('a.png', "%PDF-1.4\n")],
    'missing' => [fn () => null],
]);

test('images larger than the limit are refused', function () {
    actingAsRole(Role::Admin);
    config(['pixora.content_images.max_kb' => 1]);

    $this->postJson(route('admin.content-images.store'), ['image' => TestImages::pngUpload('besar.png', 400, 400)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image' => 'Ukuran gambar maksimal']);
});

test('only content managers can upload', function (Role $role) {
    actingAsRole($role);

    $this->postJson(route('admin.content-images.store'), ['image' => TestImages::pngUpload()])->assertForbidden();
})->with([Role::Staff, Role::Instructor, Role::Student]);

test('guests cannot upload', function () {
    $this->postJson(route('admin.content-images.store'), ['image' => TestImages::pngUpload()])->assertUnauthorized();
});

test('sanitizer keeps only our own uploaded images', function (string $src) {
    expect(app(HtmlSanitizer::class)->clean('<p>Teks</p><img src="'.$src.'" alt="x">'))->toBe('<p>Teks</p>');
})->with([
    'external' => 'https://evil.test/track.png',
    'protocol relative' => '//evil.test/a.png',
    'data uri' => 'data:image/png;base64,iVBORw0KGgo=',
    'other storage folder' => '/storage/course-thumbnails/'.str_repeat('a', 40).'.png',
    'path traversal' => '/storage/content-images/../avatars/'.str_repeat('a', 40).'.png',
    'wrong extension' => '/storage/content-images/'.str_repeat('a', 40).'.svg',
    'short name' => '/storage/content-images/abc.png',
]);

test('sanitizer drops image attributes other than src and alt, and an image alone is content', function () {
    $html = '<img src="'.ownImageUrl().'" alt="a" width="9999" style="position:fixed" onerror="alert(1)" class="x">';

    expect(app(HtmlSanitizer::class)->clean($html))->toBe('<img src="'.ownImageUrl().'" alt="a" />');
});

test('lesson notes and assignment instructions store images', function () {
    actingAsRole(Role::Admin);
    $course = publishableCourse();
    $lesson = Lesson::where('course_id', $course->id)->firstOrFail();
    $html = '<p>Lihat contoh:</p><img src="'.ownImageUrl().'" alt="contoh"><img src="https://evil.test/x.png">';

    $this->put(route('admin.lessons.update', $lesson), [
        'title' => $lesson->title, 'type' => 'video', 'status' => 'published',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'summary_html' => $html, 'drip_day_offset' => 0,
    ])->assertSessionHasNoErrors();

    expect($lesson->fresh()->summary_html)->toBe('<p>Lihat contoh:</p><img src="'.ownImageUrl().'" alt="contoh" />');

    $this->post(route('admin.assignments.store', $course), [
        'title' => 'Sketsa', 'instructions_html' => $html, 'due_offset_days' => 2, 'max_images' => 3, 'is_required' => true, 'status' => 'published',
    ])->assertSessionHasNoErrors();

    expect(Assignment::sole()->instructions_html)->toContain('<img src="'.ownImageUrl().'" alt="contoh" />')->not->toContain('evil');
});

test('public file URLs are root-relative so they work from any host (phones on the LAN)', function () {
    $course = Course::factory()->create(['thumbnail_path' => 'course-thumbnails/sampul.jpg']);

    expect($course->thumbnail_url)->toBe('/storage/course-thumbnails/sampul.jpg');
});
