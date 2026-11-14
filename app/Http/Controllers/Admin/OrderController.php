<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\MarkOrderPaid;
use App\Actions\Orders\RefundOrder;
use App\Actions\Payments\ReconcileOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderActionRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders for admins and staff (route middleware permission:orders.manage).
 * Mark-paid and refund are admin-only (OrderPolicy).
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Order::class);

        $search = Str::of((string) $request->query('q', ''))->squish()->limit(100, '')->toString();
        $status = OrderStatus::tryFrom((string) $request->query('status', ''));
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $orders = Order::query()
            ->with(['user:id,name,email', 'items:id,order_id,batch_id,title_snapshot,price', 'items.batch:id,name,starts_on,ends_on'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw("order_number LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('user', fn (Builder $query) => $query->whereRaw("email LIKE ? ESCAPE '!'", [$like]))))
            ->when($status, fn (Builder $query, OrderStatus $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => OrderResource::make($order)->resolve());

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => ['q' => $search ?: null, 'status' => $status?->value],
            'statuses' => array_map(fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()], OrderStatus::cases()),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['user:id,name,email', 'items.course:id,slug', 'items.batch', 'payments', 'enrollments']);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/orders/show', [
            'order' => OrderResource::make($order)->resolve(),
            'can' => [
                'markPaid' => $user->can('markPaid', $order) && ! in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true),
                'refund' => $user->can('refund', $order) && $order->status === OrderStatus::Paid,
            ],
            'gateway' => config('pixora.payments.gateway'),
        ]);
    }

    public function reconcile(Order $order, ReconcileOrder $reconcile): RedirectResponse
    {
        Gate::authorize('reconcile', $order);

        $order = $reconcile->handle($order);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Status dari gateway: {$order->status->label()}."]);

        return back();
    }

    public function markPaid(OrderActionRequest $request, Order $order, MarkOrderPaid $markPaid): RedirectResponse
    {
        Gate::authorize('markPaid', $order);

        /** @var User $admin */
        $admin = $request->user();

        $markPaid->handle($order, $admin, (string) $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order ditandai lunas dan akses kursus diberikan.']);

        return back();
    }

    public function refund(OrderActionRequest $request, Order $order, RefundOrder $refund): RedirectResponse
    {
        Gate::authorize('refund', $order);

        /** @var User $admin */
        $admin = $request->user();

        $refund->handle($order, $admin, (string) $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order ditandai refund dan akses kursus dicabut.']);

        return back();
    }
}
