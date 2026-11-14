<?php

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Lesson;
use Carbon\Carbon;

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Asia/Jakarta'))); // day 2 of a batch starting 15 Nov

afterEach(fn () => Carbon::setTestNow());

function offsetPayload(Lesson $lesson, int $offset, array $overrides = []): array
{
    return [
        'title' => $lesson->title,
        'type' => 'video',
        'status' => $lesson->status->value,
        'video_url' => 'https://www.youtube.com/watch?v=M7lc1UVf-VE',
        'summary_html' => '<p>Ringkasan</p>',
        'drip_day_offset' => $offset,
        'is_preview' => false,
        'duration_seconds' => 600,
        ...$overrides,
    ];
}

test('an open lesson cannot be moved to a later day in a batch with students', function () {
    $s = dripSetup('2026-11-15');
    $s['batch']->update(['name' => 'Batch 43']);
    $open = $s['lessons'][1]; // day 2, open since 16 Nov 00:00
    actingAsRole(Role::Admin);

    $this->put(route('admin.lessons.update', $open), offsetPayload($open, 4))
        ->assertSessionHasErrors(['drip_day_offset' => 'Lesson ini sudah terbuka untuk siswa di Batch 43. Hari buka tidak bisa dimundurkan karena lesson akan terkunci lagi. Lesson yang sudah terbuka tetap terbuka; atur jadwal batch mendatang lewat Kelola kelas bila perlu.']);

    expect($open->refresh()->drip_day_offset)->toBe(1);
});

test('moving an open lesson later is allowed when it stays open', function () {
    $s = dripSetup('2026-11-15');
    $lesson = $s['lessons'][0]; // day 1 → day 2: still open today
    actingAsRole(Role::Admin);

    $this->put(route('admin.lessons.update', $lesson), offsetPayload($lesson, 1))->assertSessionHasNoErrors();

    expect($lesson->refresh()->drip_day_offset)->toBe(1);
});

test('locked lessons, earlier days, drafts and empty batches are not restricted', function () {
    $s = dripSetup('2026-11-15');
    actingAsRole(Role::Admin);

    // Not open yet (day 3) → can move later.
    $this->put(route('admin.lessons.update', $s['lessons'][2]), offsetPayload($s['lessons'][2], 9))->assertSessionHasNoErrors();
    // Earlier is always fine.
    $this->put(route('admin.lessons.update', $s['lessons'][1]), offsetPayload($s['lessons'][1], 0))->assertSessionHasNoErrors();
    // Draft lessons were never visible to students.
    $this->put(route('admin.lessons.update', $s['draft']), offsetPayload($s['draft'], 9))->assertSessionHasNoErrors();

    // A started batch without students does not block.
    $s['enrollment']->delete();
    $this->put(route('admin.lessons.update', $s['lessons'][0]), offsetPayload($s['lessons'][0], 9))->assertSessionHasNoErrors();
});

test('upcoming batches do not block; any started batch with students does', function () {
    $s = dripSetup('2026-11-20'); // not started yet
    $lesson = $s['lessons'][0];
    actingAsRole(Role::Admin);

    $this->put(route('admin.lessons.update', $lesson), offsetPayload($lesson, 3))->assertSessionHasNoErrors();

    // A finished batch whose students keep access still counts.
    $finished = Batch::factory()->for($s['course'])->finished()->create(['name' => 'Batch 40']);
    Enrollment::factory()->create(['course_id' => $s['course']->id, 'batch_id' => $finished->id]);

    $this->put(route('admin.lessons.update', $lesson), offsetPayload($lesson, 300))
        ->assertSessionHasErrors('drip_day_offset');
});
