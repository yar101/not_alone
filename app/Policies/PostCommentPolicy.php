<?php

namespace App\Policies;

use App\Models\PostComment;
use App\Models\User;

class PostCommentPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PostComment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PostComment $comment): bool
    {
        return $user->id === $comment->user_id || $user->id === $comment->post?->user_id;
    }
}
