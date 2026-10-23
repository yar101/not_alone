<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserProfileService
{
    /**
     * Возвращает пагинированный список айдолов для категории с рандомизацией.
     */
    public function getCategoryIdols(User $user, ServiceCategory $category, Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(8, max(1, (int) $request->get('per_page', 4)));

        $seedKey = 'idol_shuffle_'.$user->id.'_'.$category->id;
        $seed = $request->session()->get($seedKey);
        if (! $seed || $page === 1) {
            $seed = (string) mt_rand();
            $request->session()->put($seedKey, $seed);
        }

        $paginator = User::where('id', '!=', $user->id)
            ->whereNull('deleted_at')
            ->where('is_banned', false)
            ->whereHas('services', fn ($q) => $q->where('is_active', true)->where('status', 'approved')->where('category_id', $category->id))
            ->orderByRaw('md5(concat(id, cast(? as text)))', [(string) $seed])
            ->with(['activeFrame'])
            ->select(['id', 'name', 'avatar_path', 'active_frame_id', 'rating'])
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'idols' => collect($paginator->items())
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar_url' => $u->avatar_url,
                    'rating' => $u->rating,
                    'active_frame' => $u->activeFrame,
                ])->values(),
            'total' => $paginator->total(),
            'page' => $page,
            'hasMore' => $paginator->hasMorePages(),
        ];
    }

    /**
     * Создание нового поста пользователя.
     */
    public function createPost(User $user, array $data, $photoFile = null): Post
    {
        $photoPath = null;
        $tempPath = null;
        $destinationPath = null;

        if ($photoFile) {
            $ext = $photoFile->getClientOriginalExtension() ?: 'jpg';
            // Store original file temporarily
            $tempPath = $photoFile->storeAs('temp/posts', uniqid().'.'.$ext, config('filesystems.default'));
            $destinationPath = "posts/{$user->id}/".\Illuminate\Support\Str::uuid().'.jpg';
            $photoPath = $tempPath;
        }

        $post = Post::create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'photo_path' => $photoPath,
        ]);

        if ($tempPath && $destinationPath) {
            \App\Jobs\ProcessImageUpload::dispatch(
                $tempPath,
                $destinationPath,
                Post::class,
                $post->id,
                'photo_path'
            );
        }

        \App\Jobs\NotifyFollowersJob::dispatch($user, $post);

        return $post;
    }

    /**
     * Удаление поста пользователя.
     */
    public function deletePost(Post $post): void
    {
        if ($post->photo_path) {
            Storage::disk(config('filesystems.default'))->delete($post->photo_path);
        }
        $post->delete();
    }

    /**
     * Создание комментария к посту.
     */
    public function createComment(User $user, Post $post, array $data): PostComment
    {
        $comment = $post->comments()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'parent_id' => ! empty($data['parent_id']) ? (int) $data['parent_id'] : null,
        ]);

        return $comment;
    }
}
