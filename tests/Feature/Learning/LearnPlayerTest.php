<?php

use App\Enums\LessonType;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Asia/Jakarta'))); // day 2 of a batch starting 15 Nov

afterEach(fn () => Carbon::setTestNow());

test('the course overview lists every lesson with its status and next unlock date', function () {
    $s = dripSetup('2026-11-15');

    $this->actingAs($s['student'])->get(route('learn.course', $s['course']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('learn/course')
            ->where('counts.total', 4)
            ->where('counts.unlocked', 3) // day 1, day 2 and the preview
            ->where('continueSlug', $s['lessons'][0]->slug)
            ->where('modules.0.lessons.0.status', 'unlocked')
            ->where('modules.0.lessons.2.status', 'locked')
            ->where('modules.0.lessons.2.day_number', 3)
            ->where('modules.0.lessons.2.unlock_at', '2026-11-17T00:00:00+07:00'));
});

test('a locked lesson page shows only its title and unlock date — no protected content', function () {
    $s = dripSetup('2026-11-15');
    $locked = $s['lessons'][2];
    $locked->update(['summary_html' => '<p>RAHASIA-RINGKASAN</p>', 'video_ref' => 'SECRETVID01']);

    $response = $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $locked]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('learn/lesson')
            ->where('lesson.status', 'locked')
            ->where('content', null));

    expect($response->getContent())
        ->not->toContain('SECRETVID01')
        ->not->toContain('RAHASIA-RINGKASAN');
});

test('a locked live lesson never exposes the meeting link', function () {
    $s = dripSetup('2026-11-15');
    $locked = $s['lessons'][2];
    $locked->update(['type' => LessonType::Live, 'video_ref' => null, 'video_provider' => null]);
    LiveSession::factory()->for($locked)->create(['meeting_url' => 'https://meet.example.test/rahasia-123']);

    $response = $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $locked]))->assertOk();
    expect($response->getContent())->not->toContain('rahasia-123');

    Carbon::setTestNow(Carbon::parse('2026-11-17 00:00:01', 'Asia/Jakarta'));

    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $locked]))
        ->assertInertia(fn (Assert $page) => $page->where('content.live.meeting_url', 'https://meet.example.test/rahasia-123'));
});

test('an open lesson sends the nocookie embed and the sanitised summary, never the raw video ref field', function () {
    $s = dripSetup('2026-11-15');
    $open = $s['lessons'][1];
    $open->update(['summary_html' => '<p>Ringkasan hari dua</p>']);

    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $open]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('lesson.status', 'unlocked')
            ->where('content.embed_url', fn (string $url) => str_starts_with($url, 'https://www.youtube-nocookie.com/embed/'.$open->video_ref))
            ->where('content.summary_html', '<p>Ringkasan hari dua</p>')
            ->missing('lesson.video_ref')
            ->where('next.is_unlocked', false)
            ->where('previous.slug', $s['lessons'][0]->slug));
});

test('outline props never contain video refs, summaries or meeting links', function () {
    $s = dripSetup('2026-11-15');
    foreach ($s['lessons'] as $lesson) {
        $lesson->update(['summary_html' => '<p>RINGKASAN-'.$lesson->id.'</p>']);
    }

    $content = $this->actingAs($s['student'])->get(route('learn.course', $s['course']))->getContent();

    foreach ($s['lessons'] as $lesson) {
        expect($content)->not->toContain($lesson->video_ref)->not->toContain('RINGKASAN-'.$lesson->id);
    }
});

test('draft lessons and lessons of archived modules are not reachable', function () {
    $s = dripSetup('2026-11-15');

    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $s['draft']]))->assertNotFound();

    $s['lessons'][0]->module->delete();
    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $s['lessons'][0]]))->assertNotFound();
});

test('a lesson of another course is not found through this course', function () {
    $s = dripSetup('2026-11-15');
    $other = dripSetup('2026-11-15');

    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course']->slug, $other['lessons'][0]->slug]))->assertNotFound();
});

test('students without an enrollment for the course cannot open the player', function () {
    $s = dripSetup('2026-11-15');
    $stranger = User::factory()->create()->assignRole('student');

    $this->actingAs($stranger)->get(route('learn.course', $s['course']))->assertNotFound();
    $this->actingAs($stranger)->get(route('learn.lesson', [$s['course'], $s['lessons'][0]]))->assertNotFound();
    $this->post(route('learn.complete', [$s['course'], $s['lessons'][0]]))->assertNotFound();
});

test('guests are sent to login', function () {
    $s = dripSetup('2026-11-15');

    $this->get(route('learn.course', $s['course']))->assertRedirect(route('login'));
});

test('a student marks an open lesson completed and can undo it; progress follows', function () {
    $s = dripSetup('2026-11-15');
    $lesson = $s['lessons'][0];

    $this->actingAs($s['student'])->post(route('learn.complete', [$s['course'], $lesson]))->assertRedirect();

    expect(LessonProgress::where('lesson_id', $lesson->id)->sole()->status->value)->toBe('completed')
        ->and($s['enrollment']->refresh()->progress_percent)->toBe(25); // 1 of 4 published lessons

    $this->actingAs($s['student'])->delete(route('learn.uncomplete', [$s['course'], $lesson]))->assertRedirect();

    expect($s['enrollment']->refresh()->progress_percent)->toBe(0);
});

test('a locked lesson cannot be marked completed', function () {
    $s = dripSetup('2026-11-15');

    $this->actingAs($s['student'])->post(route('learn.complete', [$s['course'], $s['lessons'][2]]))->assertForbidden();

    expect(LessonProgress::count())->toBe(0);
});

test('completing every lesson does not complete the enrollment (certificates come later)', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00', 'Asia/Jakarta'));
    $s = dripSetup('2026-11-15');

    foreach ([...$s['lessons'], $s['preview']] as $lesson) {
        $this->actingAs($s['student'])->post(route('learn.complete', [$s['course'], $lesson]))->assertRedirect();
    }

    $enrollment = $s['enrollment']->refresh();
    expect($enrollment->progress_percent)->toBe(100)
        ->and($enrollment->status->value)->toBe('active')
        ->and($enrollment->completed_at)->toBeNull();
});

test('a non-drip course opens every published lesson at enrollment', function () {
    $s = dripSetup('2026-11-15', ['drip_enabled' => false]);
    Enrollment::whereKey($s['enrollment']->id)->update(['batch_id' => null]);

    $this->actingAs($s['student'])->get(route('learn.lesson', [$s['course'], $s['lessons'][2]]))
        ->assertInertia(fn (Assert $page) => $page->where('lesson.status', 'unlocked')->whereNot('content', null));
});

test('the old Phase 3 learning URL redirects to the player', function () {
    $s = dripSetup('2026-11-15');

    $this->actingAs($s['student'])->get('/siswa/kursus/'.$s['course']->slug.'/belajar')
        ->assertRedirect('/belajar/'.$s['course']->slug);
});

test('the course overview runs a constant number of queries', function () {
    $s = dripSetup('2026-11-15');
    $module = Module::factory()->for($s['course'])->create(['position' => 1]);

    $count = function () use ($s) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($s['student'])->get(route('learn.course', $s['course']))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $count(); // warm the permission cache
    $before = $count();

    Lesson::factory()->count(12)->for($module)->published()->sequence(fn ($sequence) => ['position' => $sequence->index, 'drip_day_offset' => 3 + $sequence->index])
        ->create(['course_id' => $s['course']->id]);
    LessonProgress::create(['enrollment_id' => $s['enrollment']->id, 'lesson_id' => $s['lessons'][0]->id, 'status' => 'completed', 'completed_at' => now()]);

    expect($count())->toBe($before);
});
