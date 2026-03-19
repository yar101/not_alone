<?php

namespace App\Http\Controllers;

use App\Models\IdolCategoryDescription;
use App\Models\InterestCategory;
use App\Models\PersonalityTrait;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;

use App\Models\User;
use App\Models\UserLanguage;
use App\Services\IdolRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    public function show(User $user): Response
    {
        $checklistSnoozed = false;
        if ($user->profile_checklist_snoozed_until !== null) {
            $checklistSnoozed = $user->profile_checklist_snoozed_until->isFuture();
        }

        return Inertia::render('Profile/Show', [
            'profileUser' => [
                'id'               => $user->id,
                'name'             => $user->name,
                'gender'           => $user->gender,
                'age'              => $user->age,
                'birth_date'       => $user->birth_date?->toDateString(),
                'about'            => $user->about,
                'voice_url'        => $user->voice_path ? Storage::url($user->voice_path) : null,
                'avatar_url'       => $user->avatar_url,
                'timezone'         => $user->timezone,
                'checklist_snoozed' => $checklistSnoozed,
            ],
            'isOwner'   => auth()->id() === $user->id,
            'isIdol'    => (bool) $user->is_idol,
            'rating'    => $user->rating,

            // Deferred group "about" — traits, interests, languages + their catalogs
            'traits'        => Inertia::defer(fn () => $user->load('traits')->traits->map(fn ($t) => ['id' => $t->id, 'name_ru' => $t->name_ru]), 'about'),
            'interests'     => Inertia::defer(fn () => $user->load('interests.category')->interests->map(fn ($i) => [
                'id'       => $i->id,
                'name_ru'  => $i->name_ru,
                'category' => ['id' => $i->category->id, 'name_ru' => $i->category->name_ru],
            ]), 'about'),
            'languages'     => Inertia::defer(fn () => $user->load('languages')->languages->pluck('language_code'), 'about'),
            'allTraits'     => Inertia::defer(fn () => PersonalityTrait::orderBy('sort_order')->get(['id', 'name_ru']), 'about'),
            'allCategories' => Inertia::defer(fn () => InterestCategory::with(['interests' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get(), 'about'),

            // Deferred group "services"
            'services'     => Inertia::defer(function () use ($user) {
                $authId  = auth()->id();
                $isOwner = $authId === $user->id;
                $query   = $user->services()->with(['category:id,name,description,image_path', 'timeUnit:id,name']);

                if (!$isOwner) {
                    $query->where('is_active', true)->where('status', 'approved');
                }

                $services = $query->orderBy('created_at')->get();

                if ($services->isEmpty()) {
                    return [];
                }

                // Idol descriptions for this user
                $categoryIds  = $services->pluck('category_id')->unique()->values();
                $descriptions = IdolCategoryDescription::where('user_id', $user->id)
                    ->whereIn('category_id', $categoryIds)
                    ->pluck('description', 'category_id');

                // Other idols per category (up to 4 random)
                $otherServices = Service::where('is_active', true)
                    ->where('status', 'approved')
                    ->where('user_id', '!=', $user->id)
                    ->whereIn('category_id', $categoryIds)
                    ->with(['user:id,name,avatar_path,rating'])
                    ->get(['id', 'user_id', 'category_id']);

                $otherIdolsByCategory = $otherServices
                    ->groupBy('category_id')
                    ->map(fn ($group) => $group
                        ->unique('user_id')
                        ->shuffle()
                        ->take(4)
                        ->map(fn ($s) => [
                            'id'         => $s->user->id,
                            'name'       => $s->user->name,
                            'avatar_url' => $s->user->avatar_url,
                            'rating'     => $s->user->rating,
                        ])
                        ->values()
                    );

                return $services->groupBy('category_id')->map(function ($group) use ($descriptions, $otherIdolsByCategory) {
                    $cat = $group->first()->category;
                    return [
                        'category' => [
                            'id'          => $cat->id,
                            'name'        => $cat->name,
                            'description' => $cat->description,
                            'image_url'   => $cat->image_path ? Storage::url($cat->image_path) : null,
                        ],
                        'idol_description' => $descriptions[$cat->id] ?? null,
                        'other_idols'      => $otherIdolsByCategory[$cat->id] ?? [],
                        'items'            => $group->map(fn (Service $s) => [
                            'id'               => $s->id,
                            'name'             => $s->name,
                            'price'            => $s->price,
                            'is_active'        => $s->is_active,
                            'status'           => $s->status,
                            'rejection_reason' => $s->rejection_reason,
                            'category_id'      => $s->category_id,
                            'time_unit'        => ['id' => $s->timeUnit->id, 'name' => $s->timeUnit->name],
                        ])->values(),
                    ];
                })->values();
            }, 'services'),
            'serviceCategories' => Inertia::defer(
                fn () => ServiceCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'name_suggestions']),
                'services'
            ),
            'serviceTimeUnits' => Inertia::defer(
                fn () => ServiceTimeUnit::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
                'services'
            ),

        ]);
    }

    public function updateAbout(Request $request): RedirectResponse
    {
        $data = $request->validate(['about' => ['nullable', 'string', 'max:200']]);
        $request->user()->update($data);
        return back();
    }

    public function updateTraits(Request $request): RedirectResponse
    {
        $data = $request->validate(['trait_ids' => ['array'], 'trait_ids.*' => ['integer', 'exists:traits,id']]);
        $request->user()->traits()->sync($data['trait_ids'] ?? []);
        return back();
    }

    public function updateInterests(Request $request): RedirectResponse
    {
        $data = $request->validate(['interest_ids' => ['array'], 'interest_ids.*' => ['integer', 'exists:interests,id']]);
        $request->user()->interests()->sync($data['interest_ids'] ?? []);
        return back();
    }

    public function updateLanguages(Request $request): RedirectResponse
    {
        $data = $request->validate(['languages' => ['array'], 'languages.*' => ['string', 'max:10']]);
        $user = $request->user();
        $user->languages()->delete();
        foreach ($data['languages'] ?? [] as $code) {
            UserLanguage::create(['user_id' => $user->id, 'language_code' => $code]);
        }
        return back();
    }

    public function updateHeader(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'min:2', 'max:100', 'regex:/^\p{L}+(\s\p{L}+)?$/u'],
            'gender'     => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date', 'before:' . now()->subYears(18)->toDateString()],
            'timezone'   => ['nullable', 'string', 'max:60', 'timezone:all'],
        ], [
            'name.required' => 'Имя обязательно.',
            'name.min'      => 'Имя слишком короткое.',
            'name.max'      => 'Имя слишком длинное.',
            'name.regex'    => 'Имя должно содержать одно или два слова (только буквы).',
        ]);
        $request->user()->update($data);
        return back();
    }

    public function updateTimezone(Request $request): RedirectResponse
    {
        $data = $request->validate(['timezone' => ['nullable', 'string', 'max:60', 'timezone:all']]);
        $request->user()->update($data);
        return back();
    }

    public function updateGender(Request $request): RedirectResponse
    {
        $data = $request->validate(['gender' => ['required', 'in:male,female']]);
        $request->user()->update($data);
        return back();
    }

    public function updateBirthDate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'birth_date' => ['required', 'date', 'before:' . now()->subYears(18)->toDateString()],
        ]);
        $request->user()->update($data);
        return back();
    }

    public function updateVoice(Request $request): RedirectResponse
    {
        $request->validate(['voice' => ['required', 'file', 'mimes:webm,mp4,ogg', 'max:5120']]);
        $user  = $request->user();
        $ext   = $request->file('voice')->getClientOriginalExtension() ?: 'webm';
        $path  = $request->file('voice')->storeAs('voices', "{$user->id}.{$ext}", 'public');
        $user->update(['voice_path' => $path]);
        return back();
    }

    public function deleteVoice(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->voice_path) {
            Storage::disk('public')->delete($user->voice_path);
            $user->update(['voice_path' => null]);
        }
        return back();
    }

    public function updateChecklist(Request $request): RedirectResponse
    {
        $data = $request->validate(['snooze' => ['required', 'in:day,week,forever']]);
        $until = match ($data['snooze']) {
            'day'     => now()->addDay(),
            'week'    => now()->addWeek(),
            'forever' => now()->addYears(100),
        };
        $request->user()->update(['profile_checklist_snoozed_until' => $until]);
        return back();
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $ext  = $request->file('avatar')->getClientOriginalExtension() ?: 'jpg';
        $path = $request->file('avatar')->storeAs('avatars', "{$user->id}_" . time() . ".{$ext}", 'public');
        $user->update(['avatar_path' => $path]);
        return back();
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
        return back();
    }

    public function updateCategoryDescription(Request $request, ServiceCategory $category): RedirectResponse
    {
        $data = $request->validate(['description' => ['nullable', 'string', 'max:1000']]);

        IdolCategoryDescription::updateOrCreate(
            ['user_id' => $request->user()->id, 'category_id' => $category->id],
            ['description' => $data['description']],
        );

        return back();
    }

    public function storePost(Request $request): RedirectResponse
    {
        $request->validate([
            'body'  => ['required', 'string', 'max:377'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ]);
        $user = $request->user();
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $ext       = $request->file('photo')->getClientOriginalExtension() ?: 'jpg';
            $photoPath = $request->file('photo')->storeAs(
                "posts/{$user->id}",
                time() . '.' . $ext,
                'public'
            );
        }
        Post::create([
            'user_id'    => $user->id,
            'body'       => $request->input('body'),
            'photo_path' => $photoPath,
        ]);

        if ($user->is_idol) {
            IdolRatingService::adjust($user, 'post_published');
        }

        return back();
    }

    public function destroyPost(Request $request, Post $post): RedirectResponse
    {
        abort_if($post->user_id !== $request->user()->id, 403);
        if ($post->photo_path) {
            Storage::disk('public')->delete($post->photo_path);
        }
        $post->delete();
        return back();
    }

    public function getPosts(Request $request, User $user): JsonResponse
    {
        $paginated = $user->posts()
            ->with('user:id,name,avatar_path')
            ->withCount(['likes', 'comments'])
            ->latest()
            ->paginate(10);

        $authId = auth()->id();
        $likedIds = [];
        if ($authId) {
            $postIds = $paginated->pluck('id');
            $likedIds = PostLike::where('user_id', $authId)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->flip()
                ->all();
        }

        $data = $paginated->getCollection()->map(fn (Post $p) => [
            'id'             => $p->id,
            'body'           => $p->body,
            'photo_url'      => $p->photo_url,
            'created_at'     => $p->created_at->translatedFormat('d M Y'),
            'likes_count'    => $p->likes_count,
            'liked_by_me'    => isset($likedIds[$p->id]),
            'comments_count' => $p->comments_count,
            'author'         => [
                'id'         => $p->user->id,
                'name'       => $p->user->name,
                'avatar_url' => $p->user->avatar_url,
            ],
        ]);

        return response()->json([
            'data'          => $data,
            'next_page_url' => $paginated->nextPageUrl(),
            'current_page'  => $paginated->currentPage(),
            'last_page'     => $paginated->lastPage(),
        ]);
    }

    public function getComments(Request $request, Post $post): JsonResponse
    {
        $comments = $post->comments()
            ->with(['user:id,name,avatar_path', 'replies.user:id,name,avatar_path'])
            ->orderBy('created_at')
            ->get();

        $data = $comments->map(fn (PostComment $c) => [
            'id'         => $c->id,
            'body'       => $c->body,
            'created_at' => $c->created_at->diffForHumans(),
            'user'       => ['id' => $c->user->id, 'name' => $c->user->name, 'avatar_url' => $c->user->avatar_url],
            'replies'    => $c->replies->map(fn (PostComment $r) => [
                'id'         => $r->id,
                'body'       => $r->body,
                'created_at' => $r->created_at->diffForHumans(),
                'user'       => ['id' => $r->user->id, 'name' => $r->user->name, 'avatar_url' => $r->user->avatar_url],
                'replies'    => [],
            ])->values(),
        ]);

        return response()->json($data);
    }

    public function toggleLike(Request $request, Post $post): JsonResponse
    {
        $userId = $request->user()->id;
        $existing = PostLike::where('post_id', $post->id)->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            PostLike::create(['post_id' => $post->id, 'user_id' => $userId]);
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function storeComment(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'body'      => ['required', 'string', 'max:177'],
            'parent_id' => ['nullable', 'integer', 'exists:post_comments,id'],
        ]);

        if (!empty($data['parent_id'])) {
            $parent = PostComment::findOrFail($data['parent_id']);
            abort_if($parent->post_id !== $post->id, 422, 'Parent comment does not belong to this post.');
            abort_if($parent->parent_id !== null, 422, 'Cannot reply to a reply.');
        }

        $comment = PostComment::create([
            'post_id'   => $post->id,
            'user_id'   => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body'      => $data['body'],
        ]);

        $comment->load('user:id,name,avatar_path');

        return response()->json([
            'id'         => $comment->id,
            'body'       => $comment->body,
            'created_at' => $comment->created_at->diffForHumans(),
            'user'       => ['id' => $comment->user->id, 'name' => $comment->user->name, 'avatar_url' => $comment->user->avatar_url],
            'replies'    => [],
        ]);
    }

    public function destroyComment(Request $request, PostComment $comment): JsonResponse
    {
        abort_if($comment->user_id !== $request->user()->id, 403);
        $comment->delete();
        return response()->json(['deleted' => true]);
    }
}
