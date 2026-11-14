<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\PurchaseState;
use App\Actions\Enrollments\EnrollInFreeCourse;
use App\Actions\Orders\CreateOrder;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout summary, order creation and free enrollment.
 * Routes: auth + verified + role:student, POSTs rate-limited.
 */
class CheckoutController extends Controller
{
    public function show(Request $request, Course $course, PurchaseState $purchaseState): Response|RedirectResponse
    {
        Gate::authorize('view', $course);

        /** @var User $user */
        $user = $request->user();
        $state = $purchaseState->handle($user, $course);

        return match ($state['state']) {
            'enrolled' => redirect($state['learn_url'] ?? route('student.courses.index')),
            'pending_order' => redirect($state['order_url'] ?? route('student.orders.index')),
            default => Inertia::render('checkout/show', [
                'course' => [
                    'id' => $course->id,
                    'slug' => $course->slug,
                    'title' => $course->title,
                    'summary' => $course->summary,
                    'thumbnail_url' => $course->thumbnail_url,
                    'price' => $course->price,
                    'is_free' => $course->isFree(),
                    'access_duration_days' => $course->access_duration_days,
                ],
                'buyer' => ['name' => $user->name, 'email' => $user->email],
                'expiryHours' => (int) config('pixora.payments.order_expiry_hours'),
            ]),
        };
    }

    public function store(Request $request, Course $course, CreateOrder $createOrder): RedirectResponse
    {
        Gate::authorize('view', $course);

        /** @var User $user */
        $user = $request->user();

        // Only the course comes from the URL; the price is read from the database.
        $order = $createOrder->handle($user, $course);

        return to_route('student.orders.show', $order)->with('autoPay', true);
    }

    public function enrollFree(Request $request, Course $course, EnrollInFreeCourse $enroll): RedirectResponse
    {
        Gate::authorize('view', $course);

        /** @var User $user */
        $user = $request->user();

        $enroll->handle($user, $course);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Anda terdaftar di \"{$course->title}\"."]);

        return to_route('student.courses.learn', $course);
    }
}
