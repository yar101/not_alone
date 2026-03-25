<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id'   => Post::factory(),
            'user_id'   => User::factory(),
            'parent_id' => null,
            'body'       => fake('ru_RU')->realText(fake()->numberBetween(15, 170)),
        ];
    }

    public function replyTo(int $parentId, int $postId): static
    {
        return $this->state([
            'parent_id' => $parentId,
            'post_id'   => $postId,
        ]);
    }
}
