<?php

use App\Actions\Assignments\SetDeadlineOverride;
use App\Actions\Assignments\StoreImages;
use App\Enums\Role;
use App\Jobs\DeleteStoredFiles;
use App\Models\Assignment;
use App\Models\Attachment;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\SubmissionComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestImages;

beforeEach(function () {
    Storage::fake('assignments');
    Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'Asia/Jakarta')); // day 1 of the batch
    $this->s = dripSetup('2026-11-15');
    // Lesson day 1, due 16 Nov 23:59:59 WIB.
    $this->assignment = Assignment::factory()->forLesson($this->s['lessons'][0])->create(['due_offset_days' => 1, 'max_images' => 3]);
});

afterEach(fn () => Carbon::setTestNow());

function submitTo(Assignment $assignment, array $files, array $extra = [])
{
    return test()->post(route('learn.assignments.submit', [$assignment->course->slug, $assignment->id]), ['images' => $files, ...$extra]);
}

function updateSubmission(Assignment $assignment, array $data)
{
    return test()->post(route('learn.assignments.update', [$assignment->course->slug, $assignment->id]), $data);
}

test('a student submits images: normalised, stored privately under random names', function () {
    $this->actingAs($this->s['student']);

    submitTo($this->assignment, [TestImages::pngUpload('C:\\Users\\siswa\\Desktop\\tugas saya.png', 3600, 900, withMetadata: true)], ['body' => 'Ini karya saya'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $submission = Submission::with('attachments')->sole();
    $image = $submission->attachments->sole();

    expect($submission->body)->toBe('Ini karya saya')
        ->and($submission->status->value)->toBe('submitted')
        ->and($image->original_name)->toBe('tugas saya.png') // no client path
        ->and($image->path)->toMatch('#^submissions/\d+/\d+/[A-Za-z0-9]{40}\.png$#')
        ->and([$image->width, $image->height])->toBe([3000, 750]);

    Storage::disk('assignments')->assertExists([$image->path, $image->thumbnail_path]);
    $stored = Storage::disk('assignments')->get($image->path);
    expect($stored)->not->toContain(TestImages::GPS_MARKER)->not->toContain('eXIf')->not->toContain('tEXt');
});

test('EXIF (incl. GPS) is removed from JPEG uploads', function () {
    $this->actingAs($this->s['student']);
    $jpeg = TestImages::jpegWithExif();
    expect($jpeg)->toContain(TestImages::GPS_MARKER);

    submitTo($this->assignment, [TestImages::upload($jpeg, 'foto.jpg', 'image/jpeg')])->assertSessionHasNoErrors();

    $image = Attachment::sole();
    $stored = Storage::disk('assignments')->get($image->path);
    expect($image->mime)->toBe('image/jpeg')
        ->and($stored)->not->toContain(TestImages::GPS_MARKER)
        ->and($stored)->not->toContain('Exif');
})->skip(fn () => ! TestImages::gdSupportsJpeg(), 'GD without JPEG support (CI has it).');

test('files are checked by content, not extension', function (Closure $file) {
    $this->actingAs($this->s['student']);

    submitTo($this->assignment, [$file()])->assertSessionHasErrors(['images.0' => 'File harus berupa gambar JPEG, PNG, atau WebP.']);

    expect(Submission::count())->toBe(0);
    Storage::disk('assignments')->assertDirectoryEmpty('/');
})->with([
    'svg renamed to .jpg' => [fn () => TestImages::upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'gambar.jpg', 'image/jpeg')],
    'pdf' => [fn () => TestImages::upload("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF", 'tugas.pdf', 'application/pdf')],
    'text with .jpg extension' => [fn () => TestImages::upload('bukan gambar sama sekali', 'foto.jpg', 'image/jpeg')],
    'gif' => [fn () => TestImages::upload(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'a.gif', 'image/gif')],
]);

test('at least one image, at most max_images, each within the size limit', function () {
    $this->actingAs($this->s['student']);

    submitTo($this->assignment, [])->assertSessionHasErrors(['images' => 'Unggah minimal 1 gambar.']);
    submitTo($this->assignment, array_map(fn () => TestImages::pngUpload(), range(1, 4)))->assertSessionHasErrors('images');

    config(['pixora.assignments.max_image_kb' => 1]);
    submitTo($this->assignment, [TestImages::pngUpload('besar.png', 400, 400)])->assertSessionHasErrors(['images.0' => 'Ukuran setiap gambar maksimal 1 KB.']);

    expect(Submission::count())->toBe(0);
});

test('an assignment on a locked lesson cannot be submitted', function () {
    $locked = Assignment::factory()->forLesson($this->s['lessons'][2])->create(); // opens 17 Nov
    $this->actingAs($this->s['student']);

    submitTo($locked, [TestImages::pngUpload()])->assertSessionHasErrors(['images' => 'Tugas ini belum terbuka untuk Anda.']);
    Storage::disk('assignments')->assertDirectoryEmpty('/');
});

test('submitting after the deadline is allowed but late — and final', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 00:00:00', 'Asia/Jakarta'));
    $this->actingAs($this->s['student']);

    submitTo($this->assignment, [TestImages::pngUpload()])->assertSessionHasNoErrors();

    $this->get(route('learn.lesson', [$this->s['course'], $this->s['lessons'][0]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignment.state', 'late')
            ->where('assignment.state_label', 'Terlambat')
            ->where('assignment.submission.can_edit', false));

    updateSubmission($this->assignment, ['body' => 'ubah'])
        ->assertSessionHasErrors(['images' => 'Tenggat sudah lewat; pengumpulan tidak bisa diubah lagi.']);

    submitTo($this->assignment, [TestImages::pngUpload()])->assertSessionHasErrors(['images' => 'Anda sudah mengumpulkan tugas ini.']);
});

test('before the deadline a student can replace images; old files are removed after commit', function () {
    $this->actingAs($this->s['student']);
    submitTo($this->assignment, [TestImages::pngUpload('a.png'), TestImages::pngUpload('b.png')]);
    [$first, $second] = Attachment::orderBy('id')->get()->all();

    updateSubmission($this->assignment, ['remove' => [$first->id], 'images' => [TestImages::pngUpload('c.png')], 'body' => 'versi 2'])
        ->assertSessionHasNoErrors();

    $names = Attachment::orderBy('id')->pluck('original_name')->all();
    expect($names)->toBe(['b.png', 'c.png'])
        ->and(Submission::sole()->body)->toBe('versi 2');
    Storage::disk('assignments')->assertMissing([$first->path, $first->thumbnail_path]);
    Storage::disk('assignments')->assertExists($second->path);

    // Removing every image is refused.
    $ids = Attachment::pluck('id')->all();
    updateSubmission($this->assignment, ['remove' => $ids])->assertSessionHasErrors('images');
});

test('the file deletion job is queued after commit with the old paths', function () {
    $this->actingAs($this->s['student']);
    submitTo($this->assignment, [TestImages::pngUpload('a.png')]);
    $old = Attachment::sole();

    Queue::fake();
    updateSubmission($this->assignment, ['remove' => [$old->id], 'images' => [TestImages::pngUpload('b.png')]])->assertSessionHasNoErrors();

    Queue::assertPushed(DeleteStoredFiles::class, fn (DeleteStoredFiles $job) => $job->disk === 'assignments'
        && $job->paths === [$old->path, $old->thumbnail_path]);
});

test('a failed database write leaves no orphan files', function () {
    $store = app(StoreImages::class);

    expect(fn () => $store->store([TestImages::pngUpload(), TestImages::pngUpload()], 'submissions/x', 'images', $this->s['student'], function (array $rows) {
        expect($rows)->toHaveCount(2);
        Storage::disk('assignments')->assertExists($rows[0]['path']);

        throw new RuntimeException('database down');
    }))->toThrow(RuntimeException::class, 'database down');

    expect(Storage::disk('assignments')->allFiles())->toBe([]);
});

test('a reviewed submission is locked even before the deadline', function () {
    $this->actingAs($this->s['student']);
    submitTo($this->assignment, [TestImages::pngUpload()]);
    Submission::sole()->update(['status' => 'reviewed', 'reviewed_at' => now()]);

    updateSubmission($this->assignment, ['body' => 'x'])->assertSessionHasErrors(['images' => 'Pengumpulan sudah direview dan terkunci.']);
});

test('an extension lets a late submission become on time', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 08:00', 'Asia/Jakarta'));
    $this->actingAs($this->s['student']);
    submitTo($this->assignment, [TestImages::pngUpload()]);

    app(SetDeadlineOverride::class)->set($this->assignment, null, $this->s['enrollment'], Carbon::parse('2026-11-18 23:59', 'Asia/Jakarta'), User::factory()->create());

    $this->actingAs($this->s['student'])->get(route('learn.lesson', [$this->s['course'], $this->s['lessons'][0]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignment.state', 'on_time')
            ->where('assignment.deadline.is_extended', true)
            ->where('assignment.submission.can_edit', true));
});

test('the lesson page shows the assignment only when the lesson is open, without storage paths', function () {
    $this->actingAs($this->s['student']);
    submitTo($this->assignment, [TestImages::pngUpload()]);
    Assignment::factory()->forLesson($this->s['lessons'][2])->create(['title' => 'Tugas terkunci']);
    $image = Attachment::sole();

    $response = $this->get(route('learn.lesson', [$this->s['course'], $this->s['lessons'][0]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignment.assignment.id', $this->assignment->id)
            ->where('assignment.deadline.due_at', '2026-11-16T23:59:59+07:00')
            ->where('assignment.state', 'on_time')
            ->where('assignment.submission.images.0.url', route('attachments.show', $image))
            ->missing('assignment.submission.images.0.path'));
    expect($response->getContent())->not->toContain($image->path)->not->toContain('submissions/');

    $locked = $this->get(route('learn.lesson', [$this->s['course'], $this->s['lessons'][2]]))
        ->assertInertia(fn (Assert $page) => $page->where('assignment', null));
    expect($locked->getContent())->not->toContain('Tugas terkunci');
});

test('draft assignments are not shown to students', function () {
    $this->assignment->update(['status' => 'draft']);
    $this->actingAs($this->s['student']);

    $this->get(route('learn.lesson', [$this->s['course'], $this->s['lessons'][0]]))
        ->assertInertia(fn (Assert $page) => $page->where('assignment', null));
    submitTo($this->assignment, [TestImages::pngUpload()])->assertSessionHasErrors('images');
});

test('files are served only to the owner and reviewers, with safe headers', function () {
    $this->actingAs($this->s['student']);
    submitTo($this->assignment, [TestImages::pngUpload('karya.png')]);
    $image = Attachment::sole();

    $this->get(route('attachments.show', $image))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=600, private');

    $this->get(route('attachments.download', $image))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=karya.png');

    $this->get(route('attachments.thumbnail', $image))->assertOk();

    // Another student of the same course.
    $classmate = Enrollment::factory()->create(['course_id' => $this->s['course']->id, 'batch_id' => $this->s['batch']->id]);
    $this->actingAs($classmate->user)->get(route('attachments.show', $image))->assertNotFound();
    $this->actingAs($classmate->user)->get(route('attachments.download', $image))->assertNotFound();

    // Instructor of another course.
    $this->actingAs(instructorUser())->get(route('attachments.show', $image))->assertNotFound();

    // Staff and admins see everything.
    $this->actingAs(User::factory()->create()->assignRole(Role::Staff->value))->get(route('attachments.show', $image))->assertOk();

    // Guests are sent to login.
    auth()->logout();
    $this->get(route('attachments.show', $image))->assertRedirect(route('login'));
});

test('"Tugas saya" lists assignments with deadline, state and new-feedback badge', function () {
    $project = Assignment::factory()->create(['course_id' => $this->s['course']->id, 'title' => 'Proyek akhir']);
    Assignment::factory()->forLesson($this->s['lessons'][2])->create(['title' => 'Tugas hari 3']);
    $this->actingAs($this->s['student']);
    submitTo($this->assignment, [TestImages::pngUpload()]);

    $this->get(route('student.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/assignments/index')
            ->has('assignments', 3)
            ->where('assignments', fn ($rows) => collect($rows)->firstWhere('id', $this->assignment->id)['state'] === 'on_time'
                && collect($rows)->firstWhere('title', 'Tugas hari 3')['opens_at'] === '2026-11-17T00:00:00+07:00'
                && collect($rows)->firstWhere('id', $project->id)['due_at'] === '2026-11-30T23:59:59+07:00'));

    // Feedback arrives after the deadline → badge until the student opens it.
    Carbon::setTestNow(Carbon::parse('2026-11-17 09:00', 'Asia/Jakarta'));
    SubmissionComment::factory()->create(['submission_id' => Submission::sole()->id]);

    $this->get(route('student.assignments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('assignments.0.id', $this->assignment->id)->where('assignments.0.has_new_feedback', true));

    $this->get(route('learn.lesson', [$this->s['course'], $this->s['lessons'][0]]))
        ->assertInertia(fn (Assert $page) => $page->where('assignment.state', 'feedback')->where('assignment.submission.had_unseen_feedback', true));

    $this->get(route('student.assignments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('assignments', fn ($rows) => collect($rows)->firstWhere('id', $this->assignment->id)['has_new_feedback'] === false));
});

test('"Tugas saya" runs a constant number of queries', function () {
    $this->actingAs($this->s['student']);

    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('student.assignments.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $count();
    $before = $count();

    foreach ([1, 2] as $i) {
        $lesson = $this->s['lessons'][$i];
        $assignment = Assignment::factory()->forLesson($lesson)->create();
        Submission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $this->s['enrollment']->id]);
    }
    Assignment::factory()->create(['course_id' => $this->s['course']->id]);
    SubmissionComment::factory()->count(3)->create(['submission_id' => Submission::first()->id]);

    expect($count())->toBe($before);
});
