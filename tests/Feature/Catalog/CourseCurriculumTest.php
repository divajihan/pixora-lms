<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SECURITY: on the public course page, video data may only be sent for
 * preview lessons, and draft lessons must not appear at all.
 */
beforeEach(function () {
    $this->course = Course::factory()->create();
    $this->module = Module::factory()->for($this->course)->create(['title' => 'Modul Satu', 'position' => 0]);
});

test('video references of non-preview lessons never reach the page', function () {
    Lesson::factory()->for($this->module)->video('SECRETVID01')->create(['course_id' => $this->course->id, 'is_preview' => false]);
    Lesson::factory()->for($this->module)->video('SECRETVID02')->create(['course_id' => $this->course->id, 'is_preview' => false]);

    $response = $this->get(route('courses.show', $this->course))->assertOk();

    // Nowhere in the HTML/page JSON — not as video_ref, embed or thumbnail.
    expect($response->getContent())->not->toContain('SECRETVID01')->not->toContain('SECRETVID02');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('curriculum.modules.0.lessons', 2)
        ->missing('curriculum.modules.0.lessons.0.video_ref')
        ->missing('curriculum.modules.0.lessons.0.preview')
        ->missing('curriculum.modules.0.lessons.1.preview'));
});

test('preview lessons carry a privacy-friendly embed URL but not the raw reference field', function () {
    Lesson::factory()->for($this->module)->preview()->video('PREVIEWID01')->create(['course_id' => $this->course->id]);

    $this->get(route('courses.show', $this->course))
        ->assertInertia(fn (Assert $page) => $page
            ->where('curriculum.modules.0.lessons.0.is_preview', true)
            ->where('curriculum.modules.0.lessons.0.preview.embed_url', fn (string $url) => str_starts_with($url, 'https://www.youtube-nocookie.com/embed/PREVIEWID01'))
            ->missing('curriculum.modules.0.lessons.0.video_ref'));
});

test('draft lessons and deleted lessons/modules are not shown at all', function () {
    Lesson::factory()->for($this->module)->create(['course_id' => $this->course->id, 'title' => 'Terbit']);
    Lesson::factory()->for($this->module)->draft()->preview()->video('DRAFTVID001')->create(['course_id' => $this->course->id, 'title' => 'Masih Draf']);
    Lesson::factory()->for($this->module)->create(['course_id' => $this->course->id, 'title' => 'Dihapus'])->delete();

    $deletedModule = Module::factory()->for($this->course)->create(['title' => 'Modul Dihapus']);
    Lesson::factory()->for($deletedModule)->create(['course_id' => $this->course->id]);
    $deletedModule->delete();

    $draftOnlyModule = Module::factory()->for($this->course)->create(['title' => 'Modul Draf']);
    Lesson::factory()->for($draftOnlyModule)->draft()->create(['course_id' => $this->course->id]);

    $response = $this->get(route('courses.show', $this->course));

    expect($response->getContent())
        ->not->toContain('Masih Draf')
        ->not->toContain('DRAFTVID001')
        ->not->toContain('Modul Dihapus')
        ->not->toContain('Modul Draf');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('curriculum.modules', 1)
        ->has('curriculum.modules.0.lessons', 1)
        ->where('curriculum.modules.0.lessons.0.title', 'Terbit')
        ->where('curriculum.lesson_count', 1));
});

test('the curriculum shows lesson type, duration and totals in order', function () {
    $second = Module::factory()->for($this->course)->create(['title' => 'Modul Dua', 'position' => 1]);
    Lesson::factory()->for($this->module)->create(['course_id' => $this->course->id, 'duration_seconds' => 600, 'position' => 1, 'title' => 'B']);
    Lesson::factory()->for($this->module)->create(['course_id' => $this->course->id, 'duration_seconds' => 300, 'position' => 0, 'title' => 'A']);
    Lesson::factory()->for($second)->live()->create(['course_id' => $this->course->id, 'duration_seconds' => 3600, 'title' => 'Live']);

    $this->get(route('courses.show', $this->course))
        ->assertInertia(fn (Assert $page) => $page
            ->where('curriculum.modules.0.title', 'Modul Satu')
            ->where('curriculum.modules.0.lessons.0.title', 'A')
            ->where('curriculum.modules.0.lessons.1.title', 'B')
            ->where('curriculum.modules.1.lessons.0.type', 'live')
            ->where('curriculum.lesson_count', 3)
            ->where('curriculum.duration_seconds', 4500));
});

test('summary HTML and live meeting links are not exposed publicly', function () {
    Lesson::factory()->for($this->module)->live()->create(['course_id' => $this->course->id, 'summary_html' => '<p>RINGKASANRAHASIA</p>']);

    $content = $this->get(route('courses.show', $this->course))->getContent();

    expect($content)->not->toContain('RINGKASANRAHASIA')->not->toContain('meet.example.test');
});
