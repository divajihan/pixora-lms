<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Admin/staff order list (orders.manage).
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::OrdersManage->value);
    }

    /**
     * Owners see their own orders; other students get 404 (not discoverable).
     */
    public function view(User $user, Order $order): Response
    {
        return $order->user_id === $user->id || $user->can(Permission::OrdersManage->value)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && $order->isPayable();
    }

    /**
     * Ask the gateway for the latest status (owner or order managers).
     */
    public function reconcile(User $user, Order $order): Response
    {
        return $this->view($user, $order);
    }

    /**
     * Manual "mark as paid" — admins only.
     */
    public function markPaid(User $user, Order $order): bool
    {
        return $user->hasRole(Role::Admin->value);
    }

    /**
     * Refund & revoke access — admins only.
     */
    public function refund(User $user, Order $order): bool
    {
        return $user->hasRole(Role::Admin->value);
    }
}
