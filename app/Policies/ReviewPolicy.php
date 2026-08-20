<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Determine whether the user can create a review for an order.
     */
    public function create(User $user, Order $order): bool
    {
        return (int) $order->customer_id === (int) $user->id
            && $order->status === OrderStatus::Completed
            && ! Review::where('reviewer_id', $user->id)->where('idol_id', $order->idol_id)->exists();
    }

    /**
     * Determine whether the idol can dispute a review.
     */
    public function dispute(User $user, Review $review): bool
    {
        return (int) $review->idol_id === (int) $user->id;
    }
}
