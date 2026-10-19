<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return ! (method_exists($user, 'isActiveBanned') && $user->isActiveBanned());
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Post $post): bool
    {
        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            return false;
        }

        if ($user->id === $post->user_id) {
            return true;
        }

        return ! ($post->user && method_exists($post->user, 'isActiveBanned') && $post->user->isActiveBanned());
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            return false;
        }

        return (bool) $user->is_idol;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool
    {
        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            return false;
        }

        return $user->id === $post->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool
    {
        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            return false;
        }

        return $user->id === $post->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Post $post): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Post $post): bool
    {
        return false;
    }
}
