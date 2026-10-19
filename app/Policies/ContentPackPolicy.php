<?php

namespace App\Policies;

use App\Models\ContentPack;
use App\Models\User;

class ContentPackPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ContentPack $contentPack): bool
    {
        if ($user->id === $contentPack->user_id) {
            return true;
        }

        if ($contentPack->purchases()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return $contentPack->status === 'published' && $contentPack->hidden_at === null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_idol;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ContentPack $contentPack): bool
    {
        return $user->id === $contentPack->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ContentPack $contentPack): bool
    {
        return $user->id === $contentPack->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ContentPack $contentPack): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ContentPack $contentPack): bool
    {
        return false;
    }
}
