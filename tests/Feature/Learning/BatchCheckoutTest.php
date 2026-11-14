<?php

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['pixora.payments.gateway' => 'fake']);
    Carbon::setTestNow('2026-11-14 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array{0: User, 1: Course}
 */
function dripPaidCourse(int $price = 199_000): array
{
    return studentAndPaidCourse($price, ['drip_enabled' => true]);
}

test('a drip course requires choosing a batch at checkout', function () {
    [$student, $course] = dripPaidCourse();

    $this->actingAs($student)->post(route('checkout.store', $course))->assertSessionHasErrors('batch_id');

    expect(Order::count())->toBe(0);
});

test('the chosen batch is stored on the order item and enrollment', function () {
    [$student, $course] = dripPaidCourse();
    $batch = Batch::factory()->for($course)->startingOn('2026-11-20')->create();

    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => $batch->id])->assertRedirect();

    $order = Order::with('items')->sole();
    expect($order->items->sole()->batch_id)->toBe($batch->id);

    sendWebhook($order)->assertOk();

    $enrollment = Enrollment::sole();
    expect($enrollment->batch_id)->toBe($batch->id);
});

test('unavailable batches are rejected at checkout', function (Closure $makeBatch, string $message) {
    [$student, $course] = dripPaidCourse();
    $batch = $makeBatch($course);

    $this->actingAs($student)
        ->post(route('checkout.store', $course), ['batch_id' => $batch->id])
        ->assertSessionHasErrors(['batch_id' => $message]);

    expect(Order::count())->toBe(0);
})->with([
    'unpublished' => [fn (Course $course) => Batch::factory()->for($course)->unpublished()->create(), 'Batch ini tidak tersedia.'],
    'finished' => [fn (Course $course) => Batch::factory()->for($course)->finished()->create(), 'Batch ini sudah selesai.'],
    'registration closed' => [fn (Course $course) => Batch::factory()->for($course)->running()->create(['registration_closes_on' => '2026-11-13']), 'Pendaftaran batch ini sudah ditutup.'],
    'full' => [function (Course $course) {
        $batch = Batch::factory()->for($course)->upcoming()->create(['capacity' => 1]);
        Enrollment::factory()->create(['course_id' => $course->id, 'batch_id' => $batch->id]);

        return $batch;
    }, 'Kuota batch ini sudah penuh.'],
    'deleted' => [function (Course $course) {
        $batch = Batch::factory()->for($course)->upcoming()->create();
        $batch->delete();

        return $batch;
    }, 'Batch tidak ditemukan untuk kursus ini.'],
]);

test('a batch of another course is rejected', function () {
    [$student, $course] = dripPaidCourse();
    $other = Batch::factory()->upcoming()->create();

    $this->actingAs($student)
        ->post(route('checkout.store', $course), ['batch_id' => $other->id])
        ->assertSessionHasErrors(['batch_id' => 'Batch tidak ditemukan untuk kursus ini.']);
});

test('a running batch with open registration can still be joined', function () {
    [$student, $course] = dripPaidCourse();
    $batch = Batch::factory()->for($course)->running()->create();

    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => $batch->id])->assertRedirect();

    expect(Order::count())->toBe(1);
});

test('a pending order is reused only for the same batch', function () {
    [$student, $course] = dripPaidCourse();
    $first = Batch::factory()->for($course)->startingOn('2026-11-20')->create();
    $second = Batch::factory()->for($course)->startingOn('2026-12-05')->create();

    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => $first->id]);
    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => $first->id]);
    expect(Order::count())->toBe(1);

    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => $second->id]);
    expect(Order::count())->toBe(2);
});

test('a successful payment is never refused when the batch filled up meanwhile', function () {
    [$student, $course] = dripPaidCourse();
    $batch = Batch::factory()->for($course)->startingOn('2026-11-20')->create(['capacity' => 1]);

    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => $batch->id]);
    $order = Order::sole();

    // Someone else takes the last seat before the payment arrives.
    Enrollment::factory()->create(['course_id' => $course->id, 'batch_id' => $batch->id]);

    sendWebhook($order)->assertOk();

    expect($order->refresh()->status->value)->toBe('paid')
        ->and(Enrollment::where('user_id', $student->id)->sole()->batch_id)->toBe($batch->id)
        ->and($batch->refresh()->seatsTaken())->toBe(2);
});

test('drip access expiry counts from the batch end', function () {
    [$student, $course] = dripPaidCourse();
    $course->update(['access_duration_days' => 30]);
    $batch = Batch::factory()->for($course)->startingOn('2026-11-20', 10)->create(); // ends 2026-11-29

    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => $batch->id]);
    sendWebhook(Order::sole())->assertOk();

    expect(Enrollment::sole()->expires_at->toDateString())->toBe('2026-12-29');
});

test('free drip courses enroll into the chosen batch', function () {
    $student = User::factory()->create()->assignRole('student');
    $course = Course::factory()->published()->free()->create(['drip_enabled' => true]);
    $batch = Batch::factory()->for($course)->upcoming()->create();

    $this->actingAs($student)->post(route('checkout.free', $course))->assertSessionHasErrors('batch_id');

    $this->actingAs($student)
        ->post(route('checkout.free', $course), ['batch_id' => $batch->id])
        ->assertRedirect(route('learn.course', $course));

    expect(Enrollment::sole()->batch_id)->toBe($batch->id);
});

test('non-drip courses ignore the batch field', function () {
    [$student, $course] = studentAndPaidCourse();

    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => 999])->assertRedirect();

    expect(Order::with('items')->sole()->items->sole()->batch_id)->toBeNull();
});

test('the course page and checkout list published, unfinished batches with seats', function () {
    [$student, $course] = dripPaidCourse();
    $open = Batch::factory()->for($course)->upcoming()->create(['name' => 'Batch 44', 'capacity' => 10]);
    Batch::factory()->for($course)->finished()->create(['name' => 'Batch 42']);
    Batch::factory()->for($course)->unpublished()->create(['name' => 'Batch rahasia']);

    $this->actingAs($student)->get(route('courses.show', $course))
        ->assertInertia(fn (Assert $page) => $page
            ->has('purchase.batches', 1)
            ->where('purchase.batches.0.name', 'Batch 44')
            ->where('purchase.batches.0.seats_left', 10)
            ->where('purchase.batches.0.is_open', true)
            ->where('purchase.requires_batch', true));

    $this->actingAs($student)->get(route('checkout.show', ['course' => $course, 'batch' => $open->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('checkout/show')
            ->has('batches', 1)
            ->where('selectedBatchId', $open->id));
});

test('orders and invoices show the batch', function () {
    [$student, $course] = dripPaidCourse();
    $batch = Batch::factory()->for($course)->startingOn('2026-11-20')->create(['name' => 'Batch 44']);

    $this->actingAs($student)->post(route('checkout.store', $course), ['batch_id' => $batch->id]);
    $order = Order::sole();

    $this->get(route('student.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.items.0.batch.name', 'Batch 44'));

    $this->get(route('student.orders.invoice', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.items.0.batch.name', 'Batch 44'));

    $this->actingAs(actingAsRole(Role::Admin))->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.items.0.batch.name', 'Batch 44'));
});
