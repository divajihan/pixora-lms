<?php

namespace App\Http\Controllers\Student;

use App\Actions\Orders\StartPayment;
use App\Actions\Payments\HandlePaymentWebhook;
use App\Actions\Payments\ReconcileOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student order history, order status (pay / check status), invoice.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $orders = $user->orders()
            ->with(['items', 'items.batch'])
            ->latest('id')
            ->paginate(15)
            ->through(fn (Order $order) => OrderResource::make($order)->resolve());

        return Inertia::render('student/orders/index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order, StartPayment $startPayment, PaymentGateway $gateway): Response
    {
        Gate::authorize('view', $order);

        // Recreate the checkout session if needed (e.g. after a gateway switch).
        if ($order->isPayable() && $order->payment_token === null) {
            rescue(fn () => $startPayment->handle($order), report: false);
        }

        $order->load(['items.course:id,slug', 'items.batch', 'enrollments']);

        return Inertia::render('student/orders/show', [
            'order' => OrderResource::make($order)->resolve(),
            // The Snap token is only sent to the owner of a payable order.
            'payment' => $order->isPayable() && $request->user()?->can('pay', $order) ? [
                ...$gateway->clientConfig(),
                'token' => $order->payment_token,
                'redirect_url' => $order->payment_url,
            ] : null,
            'autoPay' => (bool) $request->session()->get('autoPay', false),
        ]);
    }

    /**
     * "Cek status": asks the gateway (server-side). The browser redirect is never proof of payment.
     */
    public function reconcile(Order $order, ReconcileOrder $reconcile): RedirectResponse
    {
        Gate::authorize('reconcile', $order);

        $order = $reconcile->handle($order);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Status pesanan: {$order->status->label()}."]);

        return back();
    }

    public function invoice(Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'items.batch', 'user', 'payments']);

        return Inertia::render('student/orders/invoice', [
            'order' => OrderResource::make($order)->resolve(),
            'seller' => ['name' => config('app.name'), 'url' => config('app.url')],
        ]);
    }

    /**
     * Local simulator for PAYMENT_GATEWAY=fake: sends a signed fake notification
     * through the real webhook pipeline. Not registered in production.
     */
    public function simulate(Request $request, Order $order, HandlePaymentWebhook $webhook, PaymentGateway $gateway): RedirectResponse
    {
        Gate::authorize('pay', $order);

        abort_unless($gateway instanceof FakeGateway, 404);

        $status = $request->validate([
            'status' => ['required', Rule::in(['settlement', 'pending', 'deny', 'expire', 'cancel'])],
        ])['status'];

        $result = $webhook->handle($gateway->notification($order, $status));

        Inertia::flash('toast', [
            'type' => $result['status'] === 200 ? 'success' : 'error',
            'message' => "Simulasi \"{$status}\" dikirim ke webhook ({$result['status']}).",
        ]);

        return back();
    }
}
