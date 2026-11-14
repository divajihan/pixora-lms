<?php

use App\Enums\LessonStatus;
use App\Enums\LessonType;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;

beforeEach(function () {
    actingAsRole(Role::Admin);
    $this->course = Course::factory()->draft()->create();
});

function lessonPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Pengenalan Materi',
        'type' => 'video',
        'status' => 'published',
        'video_url' => 'https://www.youtube.com/watch?v=M7lc1UVf-VE&t=30s',
        'summary_html' => '<p>Ringkasan</p>',
        'drip_day_offset' => 0,
        'is_preview' => false,
        'duration_seconds' => 600,
    ], $overrides);
}

test('modules are created in order, renamed and soft-deleted with their lessons', function () {
    $this->post(route('admin.modules.store', $this->course), ['title' => 'Modul A'])->assertSessionHasNoErrors();
    $this->post(route('admin.modules.store', $this->course), ['title' => 'Modul B']);

    [$a, $b] = $this->course->modules()->get()->all();
    expect([$a->position, $b->position])->toBe([0, 1]);

    $this->put(route('admin.modules.update', $a), ['title' => 'Modul A1'])->assertSessionHasNoErrors();
    expect($a->refresh()->title)->toBe('Modul A1');

    $lesson = Lesson::factory()->for($a)->create(['course_id' => $this->course->id]);
    $this->delete(route('admin.modules.destroy', $a))->assertSessionHasNoErrors();

    expect(Module::find($a->id))->toBeNull()
        ->and(Module::withTrashed()->find($a->id))->not->toBeNull()
        ->and(Lesson::find($lesson->id))->toBeNull()
        ->and(Lesson::withTrashed()->find($lesson->id))->not->toBeNull();
});

test('a video lesson stores only the YouTube id', function () {
    $module = Module::factory()->for($this->course)->create();

    $this->post(route('admin.lessons.store', $module), lessonPayload())->assertSessionHasNoErrors();

    $lesson = Lesson::firstOrFail();
    expect($lesson->video_provider)->toBe('youtube')
        ->and($lesson->video_ref)->toBe('M7lc1UVf-VE')
        ->and($lesson->slug)->toBe('pengenalan-materi')
        ->and($lesson->course_id)->toBe($this->course->id)
        ->and($lesson->type)->toBe(LessonType::Video);
});

test('non-YouTube video URLs are rejected', function (string $url) {
    $module = Module::factory()->for($this->course)->create();

    $this->post(route('admin.lessons.store', $module), lessonPayload(['video_url' => $url]))
        ->assertSessionHasErrors(['video_url' => 'URL video harus berupa tautan YouTube yang valid (youtube.com/watch, youtu.be, embed, atau shorts).']);

    expect(Lesson::count())->toBe(0);
})->with(['https://vimeo.com/76979871', 'https://evil.test/watch?v=M7lc1UVf-VE', 'bukan url']);

test('lesson validation rules', function (array $overrides, string $field) {
    $module = Module::factory()->for($this->course)->create();

    $this->post(route('admin.lessons.store', $module), lessonPayload($overrides))->assertSessionHasErrors($field);
})->with([
    'video without url' => [['video_url' => ''], 'video_url'],
    'negative drip offset' => [['drip_day_offset' => -1], 'drip_day_offset'],
    'unknown type' => [['type' => 'audio'], 'type'],
    'live without start' => [['type' => 'live', 'live_starts_at' => ''], 'live_starts_at'],
    'live end before start' => [['type' => 'live', 'live_starts_at' => '2026-12-01 19:00', 'live_ends_at' => '2026-12-01 18:00'], 'live_ends_at'],
    'unsafe meeting url' => [['type' => 'live', 'live_starts_at' => '2026-12-01 19:00', 'live_meeting_url' => 'javascript:alert(1)'], 'live_meeting_url'],
]);

test('summary HTML is sanitised before it is stored', function () {
    $module = Module::factory()->for($this->course)->create();

    $this->post(route('admin.lessons.store', $module), lessonPayload([
        'summary_html' => '<p onclick="steal()">Halo <strong>dunia</strong></p><script>alert(1)</script><img src=x onerror="alert(2)">',
    ]))->assertSessionHasNoErrors();

    $html = Lesson::firstOrFail()->summary_html;

    expect($html)->toBe('<p>Halo <strong>dunia</strong></p>')
        ->not->toContain('script')
        ->not->toContain('onerror')
        ->not->toContain('onclick');
});

test('a live lesson stores its schedule, and switching to video removes it', function () {
    $module = Module::factory()->for($this->course)->create();

    $this->post(route('admin.lessons.store', $module), lessonPayload([
        'type' => 'live',
        'video_url' => '',
        'live_starts_at' => '2026-12-01T19:00',
        'live_ends_at' => '2026-12-01T20:30',
        'live_platform' => 'Zoom',
        'live_meeting_url' => 'https://zoom.example.test/j/123',
        'live_notes' => 'Bawa laptop.',
    ]))->assertSessionHasNoErrors();

    $lesson = Lesson::with('liveSession')->firstOrFail();
    expect($lesson->type)->toBe(LessonType::Live)
        ->and($lesson->video_ref)->toBeNull()
        ->and($lesson->liveSession->platform)->toBe('Zoom')
        ->and($lesson->liveSession->starts_at->format('Y-m-d H:i'))->toBe('2026-12-01 19:00');

    $this->put(route('admin.lessons.update', $lesson), lessonPayload(['title' => $lesson->title]))->assertSessionHasNoErrors();

    expect($lesson->refresh()->liveSession)->toBeNull()->and($lesson->video_ref)->toBe('M7lc1UVf-VE');
});

test('lesson slugs are unique per course and stable on rename', function () {
    $module = Module::factory()->for($this->course)->create();
    $other = Module::factory()->create();

    $this->post(route('admin.lessons.store', $module), lessonPayload());
    $this->post(route('admin.lessons.store', $module), lessonPayload());
    $this->post(route('admin.lessons.store', $other), lessonPayload());

    expect(Lesson::where('course_id', $this->course->id)->pluck('slug')->all())->toBe(['pengenalan-materi', 'pengenalan-materi-2'])
        ->and(Lesson::where('course_id', $other->course_id)->value('slug'))->toBe('pengenalan-materi');

    $lesson = Lesson::where('slug', 'pengenalan-materi')->where('course_id', $this->course->id)->firstOrFail();
    $this->put(route('admin.lessons.update', $lesson), lessonPayload(['title' => 'Judul Baru']));
    expect($lesson->refresh()->slug)->toBe('pengenalan-materi');
});

test('deleting a lesson archives it (soft delete)', function () {
    $lesson = Lesson::factory()->for(Module::factory()->for($this->course))->create(['course_id' => $this->course->id]);

    $this->delete(route('admin.lessons.destroy', $lesson))->assertSessionHasNoErrors();

    expect(Lesson::find($lesson->id))->toBeNull()->and(Lesson::withTrashed()->find($lesson->id))->not->toBeNull();
});

test('the curriculum is reordered and lessons move between modules in one request', function () {
    $m1 = Module::factory()->for($this->course)->create(['position' => 0]);
    $m2 = Module::factory()->for($this->course)->create(['position' => 1]);
    $a = Lesson::factory()->for($m1)->create(['course_id' => $this->course->id, 'position' => 0]);
    $b = Lesson::factory()->for($m1)->create(['course_id' => $this->course->id, 'position' => 1]);
    $c = Lesson::factory()->for($m2)->create(['course_id' => $this->course->id, 'position' => 0]);

    $this->put(route('admin.courses.curriculum.reorder', $this->course), [
        'modules' => [
            ['id' => $m2->id, 'lessons' => [$c->id, $a->id]],
            ['id' => $m1->id, 'lessons' => [$b->id]],
        ],
    ])->assertSessionHasNoErrors();

    expect($m2->refresh()->position)->toBe(0)
        ->and($m1->refresh()->position)->toBe(1)
        ->and($a->refresh()->only(['module_id', 'position']))->toBe(['module_id' => $m2->id, 'position' => 1])
        ->and($b->refresh()->only(['module_id', 'position']))->toBe(['module_id' => $m1->id, 'position' => 0])
        ->and($c->refresh()->only(['module_id', 'position']))->toBe(['module_id' => $m2->id, 'position' => 0]);

    expect($this->course->modules()->first()->lessons->pluck('id')->all())->toBe([$c->id, $a->id]);
});

test('invalid reorder payloads change nothing', function (string $scenario) {
    $m1 = Module::factory()->for($this->course)->create(['position' => 0]);
    $m2 = Module::factory()->for($this->course)->create(['position' => 1]);
    $a = Lesson::factory()->for($m1)->create(['course_id' => $this->course->id, 'position' => 0]);
    $b = Lesson::factory()->for($m2)->create(['course_id' => $this->course->id, 'position' => 0]);
    $foreign = Lesson::factory()->create();

    $modules = match ($scenario) {
        'missing a lesson' => [['id' => $m2->id, 'lessons' => [$a->id]], ['id' => $m1->id, 'lessons' => []]],
        'missing a module' => [['id' => $m1->id, 'lessons' => [$a->id, $b->id]]],
        'lesson from another course' => [['id' => $m2->id, 'lessons' => [$b->id, $foreign->id]], ['id' => $m1->id, 'lessons' => [$a->id]]],
        'duplicate lesson' => [['id' => $m2->id, 'lessons' => [$a->id, $b->id]], ['id' => $m1->id, 'lessons' => [$a->id]]],
        'module from another course' => [['id' => $m1->id, 'lessons' => [$a->id]], ['id' => $m2->id, 'lessons' => [$b->id]], ['id' => $foreign->module_id, 'lessons' => []]],
    };

    $this->put(route('admin.courses.curriculum.reorder', $this->course), ['modules' => $modules])
        ->assertSessionHasErrors('modules');

    expect($m1->refresh()->position)->toBe(0)
        ->and($a->refresh()->module_id)->toBe($m1->id)
        ->and($b->refresh()->module_id)->toBe($m2->id)
        ->and($foreign->refresh()->course_id)->not->toBe($this->course->id);
})->with(['missing a lesson', 'missing a module', 'lesson from another course', 'duplicate lesson', 'module from another course']);

test('lessons of one course cannot be edited through another course module', function () {
    $lesson = Lesson::factory()->create();

    // Lesson routes are bound by id; the lesson keeps its own module/course.
    $this->put(route('admin.lessons.update', $lesson), lessonPayload(['title' => $lesson->title]))->assertSessionHasNoErrors();

    expect($lesson->refresh()->course_id)->toBe($lesson->module->course_id);
});

test('lesson status defaults and drip offset are stored', function () {
    $module = Module::factory()->for($this->course)->create();

    $this->post(route('admin.lessons.store', $module), lessonPayload(['status' => 'draft', 'drip_day_offset' => 7, 'is_preview' => true]));

    expect(Lesson::firstOrFail())
        ->status->toBe(LessonStatus::Draft)
        ->drip_day_offset->toBe(7)
        ->is_preview->toBeTrue();
});
