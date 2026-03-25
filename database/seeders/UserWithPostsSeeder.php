<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserWithPostsSeeder extends Seeder
{
    public function run(): void
    {
        // Создаём 10 пользователей, у каждого 15–30 постов
        $users = User::factory(10)->create();

        foreach ($users as $user) {
            $postCount = fake()->numberBetween(15, 30);
            $posts = Post::factory($postCount)->create(['user_id' => $user->id]);

            foreach ($posts as $post) {
                // Лайки: случайные пользователи лайкают пост
                $likers = $users->random(fake()->numberBetween(0, min(8, $users->count())));
                foreach ($likers as $liker) {
                    PostLike::firstOrCreate([
                        'post_id' => $post->id,
                        'user_id' => $liker->id,
                    ]);
                }

                // Комментарии: 0–8 top-level
                $topCount = fake()->numberBetween(0, 8);
                for ($i = 0; $i < $topCount; $i++) {
                    $commenter = $users->random();
                    $comment = PostComment::factory()->create([
                        'post_id' => $post->id,
                        'user_id' => $commenter->id,
                    ]);

                    // Ответы: 0–4 на каждый комментарий
                    $replyCount = fake()->numberBetween(0, 4);
                    for ($j = 0; $j < $replyCount; $j++) {
                        PostComment::factory()->replyTo($comment->id, $post->id)->create([
                            'user_id' => $users->random()->id,
                        ]);
                    }
                }
            }
        }

        $this->command->info("Создано {$users->count()} пользователей с постами, лайками и комментариями.");
    }
}
