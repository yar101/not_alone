<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return (int) $user->id === (int) $order->customer_id
            || (int) $user->id === (int) $order->idol_id;
    }

    /**
     * Determine whether the idol can accept the order.
     */
    public function accept(User $user, Order $order): bool
    {
        return (int) $user->id === (int) $order->idol_id
            && $order->status === OrderStatus::Pending;
    }

    /**
     * Determine whether the customer can pay for the order.
     */
    public function pay(User $user, Order $order): bool
    {
        return (int) $user->id === (int) $order->customer_id
            && $order->status === OrderStatus::Accepted;
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        $isParticipant = (int) $user->id === (int) $order->customer_id
            || (int) $user->id === (int) $order->idol_id;

        return $isParticipant
            && in_array($order->status, [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Paid]);
    }

    /**
     * Determine whether the user can confirm completion of the order.
     */
    public function confirmCompletion(User $user, Order $order): bool
    {
        $isParticipant = (int) $user->id === (int) $order->customer_id
            || (int) $user->id === (int) $order->idol_id;

        return $isParticipant
            && $order->status === OrderStatus::Paid;
    }

    /**
     * Determine whether the customer can dispute the order.
     */
    public function dispute(User $user, Order $order): bool
    {
        return (int) $user->id === (int) $order->customer_id
            && in_array($order->status, [OrderStatus::Paid, OrderStatus::Completed]);
    }

    /**
     * Determine whether the customer can add an item to the pending order.
     */
    public function addItem(User $user, Order $order): bool
    {
        return (int) $user->id === (int) $order->customer_id
            && $order->status === OrderStatus::Pending;
    }
}
