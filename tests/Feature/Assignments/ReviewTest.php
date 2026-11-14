<?php

use App\Enums\CourseInstructorRole;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentDeadlineOverride;
use App\Models\Attachment;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\SubmissionComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestImages;

function wibTime(string $time): Carbon
{
    return Carbon::parse($time, 'Asia/Jakarta');
}

beforeEach(function () {
    Storage::fake('assignments');
    Carbon::setTestNow(wibTime('2026-11-15 10:00'));
    $this->s = dripSetup('2026-11-15');
    $this->instructor = instructorUser();
    $this->s['course']->instructors()->attach($this->instructor, ['role' => CourseInstructorRole::Lead->value]);
    // Due 16 Nov 23:59:59 WIB.
    $this->assignment = Assignment::factory()->forLesson($this->s['lessons'][0])->create(['due_offset_days' => 1]);

    $this->actingAs($this->s['student'])
        ->post(route('learn.assignments.submit', [$this->s['course']->slug, $this->assignment->id]), ['images' => [TestImages::pngUpload()]])
        ->assertSessionHasNoErrors();
    $this->submission = Submission::sole();
});

afterEach(fn () => Carbon::setTestNow());

function comment(Submission $submission, array $data = ['body' => 'Bagus, perbaiki komposisinya.'])
{
    return test()->post(route('submissions.comments.store', $submission), $data);
}

test('feedback is refused before the deadline', function () {
    $this->actingAs($this->instructor);

    comment($this->submission)->assertSessionHasErrors('body');

    expect(SubmissionComment::count())->toBe(0);
    $this->get(route('submissions.show', $this->submission))
        ->assertInertia(fn (Assert $page) => $page->where('canComment', false)->where('commentBlocker', fn ($text) => str_starts_with($text, 'Feedback baru bisa diberikan setelah tenggat')));
});

test('the first instructor comment marks the submission reviewed and locks it', function () {
    Carbon::setTestNow(wibTime('2026-11-17 09:00'));
    $this->actingAs($this->instructor);

    comment($this->submission, ['body' => 'Lihat coretan saya.', 'images' => [TestImages::pngUpload('coretan.png', withMetadata: true)]])
        ->assertSessionHasNoErrors();

    $submission = $this->submission->refresh();
    $comment = SubmissionComment::sole();
    expect($submission->status->value)->toBe('reviewed')
        ->and($submission->reviewed_by)->toBe($this->instructor->id)
        ->and($submission->reviewed_at)->not->toBeNull()
        ->and($comment->author_role->value)->toBe('instructor')
        ->and($comment->attachments)->toHaveCount(1);

    // Comment images are normalised the same way (metadata removed).
    $image = $comment->attachments->sole();
    expect(Storage::disk('assignments')->get($image->path))->not->toContain(TestImages::GPS_MARKER);

    // Locked for the student — even with an extension that reopens the deadline.
    AssignmentDeadlineOverride::create(['assignment_id' => $this->assignment->id, 'enrollment_id' => $this->s['enrollment']->id, 'due_at' => wibTime('2026-11-20 23:59')]);
    $this->actingAs($this->s['student'])
        ->post(route('learn.assignments.update', [$this->s['course']->slug, $this->assignment->id]), ['body' => 'ubah'])
        ->assertSessionHasErrors(['images' => 'Pengumpulan sudah direview dan terkunci.']);

    // The student sees the feedback and its image, and cannot reply.
    $this->actingAs($this->s['student'])->get(route('attachments.show', $image))->assertOk();
    $this->actingAs($this->s['student'])->post(route('submissions.comments.store', $this->submission), ['body' => 'terima kasih'])->assertForbidden();
});

test('late submissions get no feedback (UI and server)', function () {
    Carbon::setTestNow(wibTime('2026-11-17 08:00'));
    $late = Enrollment::factory()->create(['course_id' => $this->s['course']->id, 'batch_id' => $this->s['batch']->id]);
    $this->actingAs($late->user)
        ->post(route('learn.assignments.submit', [$this->s['course']->slug, $this->assignment->id]), ['images' => [TestImages::pngUpload()]]);
    $lateSubmission = Submission::where('enrollment_id', $late->id)->sole();

    $this->actingAs($this->instructor)->get(route('submissions.show', $lateSubmission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.is_late', true)
            ->where('canComment', false)
            ->where('commentBlocker', 'Terlambat - tidak mendapat feedback.'));

    comment($lateSubmission)->assertSessionHasErrors(['body' => 'Terlambat - tidak mendapat feedback.']);
    expect(SubmissionComment::count())->toBe(0);
});

test('staff see and comment on every course but do not mark reviewed nor change assignments or deadlines', function () {
    Carbon::setTestNow(wibTime('2026-11-17 09:00'));
    $staff = User::factory()->create()->assignRole(Role::Staff->value);
    $this->actingAs($staff);

    $this->get(route('submissions.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('submissions/index')->where('submissions.data.0.id', $this->submission->id));
    $this->get(route('submissions.show', $this->submission))->assertOk();
    $this->get(route('classes.assignments.index', $this->s['course']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManageDeadlines', false));

    comment($this->submission, ['body' => 'Catatan dari staf.'])->assertSessionHasNoErrors();
    expect(SubmissionComment::sole()->author_role->value)->toBe('staff')
        ->and($this->submission->refresh()->status->value)->toBe('submitted');

    $this->post(route('classes.assignments.deadline.set', [$this->s['course'], $this->assignment]), ['batch_id' => $this->s['batch']->id, 'due_at' => '2026-11-25 23:59'])->assertNotFound();
    $this->put(route('admin.assignments.update', $this->assignment), ['title' => 'X', 'due_offset_days' => 1, 'max_images' => 1, 'status' => 'published'])->assertForbidden();
    expect(AssignmentDeadlineOverride::count())->toBe(0);

    // The student sees the staff comment labelled "Staf".
    $this->actingAs($this->s['student'])->get(route('learn.lesson', [$this->s['course'], $this->s['lessons'][0]]))
        ->assertInertia(fn (Assert $page) => $page->where('assignment.submission.comments.0.role_label', 'Staf'));
});

test('instructors only reach their own courses; students and guests never reach reviewer pages', function () {
    Carbon::setTestNow(wibTime('2026-11-17 09:00'));
    $other = instructorUser();

    $this->actingAs($other)->get(route('submissions.show', $this->submission))->assertNotFound();
    $this->actingAs($other)->get(route('classes.assignments.index', $this->s['course']))->assertNotFound();
    $this->actingAs($other)->get(route('classes.assignments.show', [$this->s['course'], $this->assignment]))->assertNotFound();
    $this->actingAs($other)->post(route('submissions.comments.store', $this->submission), ['body' => 'x'])->assertNotFound();
    $this->actingAs($other)->get(route('submissions.index'))->assertForbidden();

    $this->actingAs($this->s['student'])->get(route('submissions.show', $this->submission))->assertNotFound();
    $this->actingAs($this->s['student'])->get(route('classes.assignments.index', $this->s['course']))->assertNotFound();

    expect(SubmissionComment::count())->toBe(0);
});

test('authors edit ("diedit") and soft-delete only their own comments', function () {
    Carbon::setTestNow(wibTime('2026-11-17 09:00'));
    $staff = User::factory()->create()->assignRole(Role::Staff->value);
    $this->actingAs($this->instructor);
    comment($this->submission, ['body' => 'Versi awal']);
    $mine = SubmissionComment::sole();

    $this->put(route('submissions.comments.update', $mine), ['body' => 'Versi revisi'])->assertSessionHasNoErrors();
    expect($mine->refresh())->body->toBe('Versi revisi')->edited_at->not->toBeNull();

    $this->actingAs($staff)->put(route('submissions.comments.update', $mine), ['body' => 'dibajak'])->assertNotFound();
    $this->actingAs($staff)->delete(route('submissions.comments.destroy', $mine))->assertNotFound();

    $this->actingAs($this->instructor)->delete(route('submissions.comments.destroy', $mine))->assertRedirect();
    expect(SubmissionComment::count())->toBe(0)->and(SubmissionComment::withTrashed()->count())->toBe(1);

    // The student no longer sees it (nor its images).
    $this->actingAs($this->s['student'])->get(route('learn.lesson', [$this->s['course'], $this->s['lessons'][0]]))
        ->assertInertia(fn (Assert $page) => $page->has('assignment.submission.comments', 0));
});

test('images of a deleted comment are hidden from the student but visible to reviewers', function () {
    Carbon::setTestNow(wibTime('2026-11-17 09:00'));
    $this->actingAs($this->instructor);
    comment($this->submission, ['images' => [TestImages::pngUpload('coretan.png')]])->assertSessionHasNoErrors();
    $image = Attachment::where('attachable_type', 'submission_comment')->sole();
    SubmissionComment::sole()->delete();

    $this->actingAs($this->s['student'])->get(route('attachments.show', $image))->assertNotFound();
    $this->actingAs($this->instructor)->get(route('attachments.show', $image))->assertOk();
});

test('a comment needs text or an image; at most comment_max_images', function () {
    Carbon::setTestNow(wibTime('2026-11-17 09:00'));
    $this->actingAs($this->instructor);

    comment($this->submission, ['body' => '   '])->assertSessionHasErrors(['body' => 'Tulis komentar atau lampirkan gambar.']);
    comment($this->submission, ['images' => array_map(fn () => TestImages::pngUpload(), range(1, 6))])->assertSessionHasErrors('images');
    comment($this->submission, ['images' => [TestImages::upload('<svg/>', 'x.png')]])->assertSessionHasErrors('images.0');
});

test('instructors extend deadlines per batch and per student; past dates are refused', function () {
    $this->actingAs($this->instructor);
    $url = route('classes.assignments.deadline.set', [$this->s['course'], $this->assignment]);

    $this->post($url, ['batch_id' => $this->s['batch']->id, 'due_at' => '2026-11-14 10:00'])->assertSessionHasErrors(['due_at' => 'Tenggat baru harus di masa depan.']);
    $this->post($url, ['batch_id' => $this->s['batch']->id, 'due_at' => '2026-11-20 23:59'])->assertSessionHasNoErrors();
    $this->post($url, ['enrollment_id' => $this->s['enrollment']->id, 'due_at' => '2026-11-22 12:00'])->assertSessionHasNoErrors();
    $this->post($url, [])->assertSessionHasErrors('batch_id');

    expect(AssignmentDeadlineOverride::pluck('scope_key')->sort()->values()->all())
        ->toBe(['batch:'.$this->s['batch']->id, 'enrollment:'.$this->s['enrollment']->id]);

    $this->get(route('classes.assignments.show', [$this->s['course'], $this->assignment]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignment.deadline.override', '2026-11-20T23:59:00+07:00')
            ->where('students.data.0.due_at', '2026-11-22T12:00:00+07:00')
            ->where('students.data.0.deadline_source', 'enrollment'));

    $this->delete(route('classes.assignments.deadline.reset', [$this->s['course'], $this->assignment]), ['enrollment_id' => $this->s['enrollment']->id])->assertSessionHasNoErrors();
    expect(AssignmentDeadlineOverride::count())->toBe(1);

    // Admins can too.
    actingAsRole(Role::Admin);
    $this->delete(route('classes.assignments.deadline.reset', [$this->s['course'], $this->assignment]), ['batch_id' => $this->s['batch']->id])->assertSessionHasNoErrors();
    expect(AssignmentDeadlineOverride::count())->toBe(0);
});

test('the overview counts not submitted, awaiting review, reviewed and late per batch', function () {
    $students = Enrollment::factory()->count(4)->create(['course_id' => $this->s['course']->id, 'batch_id' => $this->s['batch']->id]);
    Submission::factory()->create(['assignment_id' => $this->assignment->id, 'enrollment_id' => $students[0]->id, 'submitted_at' => wibTime('2026-11-15 12:00')]);
    Submission::factory()->reviewed()->create(['assignment_id' => $this->assignment->id, 'enrollment_id' => $students[1]->id, 'submitted_at' => wibTime('2026-11-15 12:00')]);
    Submission::factory()->create(['assignment_id' => $this->assignment->id, 'enrollment_id' => $students[2]->id, 'submitted_at' => wibTime('2026-11-17 01:00')]);
    Carbon::setTestNow(wibTime('2026-11-17 09:00'));

    $this->actingAs($this->instructor)->get(route('classes.assignments.index', $this->s['course']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('classes/assignments')
            ->where('selectedBatchId', $this->s['batch']->id)
            ->where('assignments.0.counts', ['not_submitted' => 1, 'awaiting_review' => 2, 'needs_review' => 2, 'reviewed' => 1, 'late' => 1]));

    $this->actingAs($this->instructor)->get(route('classes.assignments.show', [$this->s['course'], $this->assignment, 'status' => 'terlambat']))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 1)->where('students.data.0.review_state', 'late'));

    $this->actingAs($this->instructor)->get(route('classes.assignments.show', [$this->s['course'], $this->assignment, 'status' => 'perlu-review']))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 2));

    // Instructor dashboard queue: on time, past deadline, no instructor feedback.
    $this->actingAs($this->instructor)->get(route('instructor.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('toReview.count', 2));
});

test('the student list is searchable, paginated and runs a constant number of queries', function () {
    $this->actingAs($this->instructor);
    $url = route('classes.assignments.show', [$this->s['course'], $this->assignment]);

    $count = function () use ($url) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $count();
    $before = $count();

    $more = Enrollment::factory()->count(24)->create(['course_id' => $this->s['course']->id, 'batch_id' => $this->s['batch']->id]);
    foreach ($more->take(10) as $enrollment) {
        Submission::factory()->create(['assignment_id' => $this->assignment->id, 'enrollment_id' => $enrollment->id]);
    }
    AssignmentDeadlineOverride::create(['assignment_id' => $this->assignment->id, 'enrollment_id' => $more[0]->id, 'due_at' => now()->addWeek()]);

    expect($count())->toBe($before);

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('students.total', 25)->has('students.data', 20));
    $this->get(route('classes.assignments.show', [$this->s['course'], $this->assignment, 'q' => $this->s['student']->email]))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 1));
});
