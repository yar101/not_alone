<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContentPackResource;
use App\Http\Resources\ServiceResource;
use App\Models\ChatBlock;
use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\IdolCategoryDescription;
use App\Models\InterestCategory;
use App\Models\InterestSuggestion;
use App\Models\PersonalityTrait;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\TraitSuggestion;
use App\Models\User;
use App\Models\UserLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    public function __construct(private \App\Services\UserProfileService $profileService) {}

    public function show(User $user): Response
    {
        $checklistSnoozed = false;
        if ($user->profile_checklist_snoozed_until !== null) {
            $checklistSnoozed = $user->profile_checklist_snoozed_until->isFuture();
        }

        return Inertia::render('Profile/Show', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'gender' => $user->gender,
                'age' => $user->age,
                'birth_date' => $user->birth_date?->toDateString(),
                'about' => $user->about,
                'voice_url' => $user->voice_path ? Storage::url($user->voice_path) : null,
                'avatar_url' => $user->avatar_url,
                'timezone' => $user->timezone,
                'checklist_snoozed' => $checklistSnoozed,
                'is_banned' => $user->isActiveBanned(),
                'active_frame' => $user->activeFrame,
                'is_newbie' => cache()->remember("user_is_newbie_{$user->id}", 3600, fn () => \App\Models\Order::where('idol_id', $user->id)->where('status', 'completed')->count() < 25
                ),
            ],
            'isOwner' => auth()->id() === $user->id,
            'isIdol' => (bool) $user->is_idol,
            'rating' => $user->rating,
            'isFollowing' => auth()->check() ? auth()->user()->isFollowing($user->id) : false,
            'isBlockedByIdol' => auth()->check()
                ? ChatBlock::active()
                    ->where('blocker_id', $user->id)
                    ->where('blocked_id', auth()->id())
                    ->exists()
                : false,

            // Deferred group "about" — traits, interests, languages + their catalogs
            'traits' => Inertia::defer(fn () => $user->load('traits')->traits->map(fn ($t) => [
                'id' => $t->id,
                'name_ru' => $t->getTranslation('name', 'ru'),
                'name_en' => $t->getTranslation('name', 'en', false) ?: null,
            ]), 'about'),
            'interests' => Inertia::defer(fn () => $user->load('interests.category')->interests->map(fn ($i) => [
                'id' => $i->id,
                'name_ru' => $i->getTranslation('name', 'ru'),
                'name_en' => $i->getTranslation('name', 'en', false) ?: null,
                'category' => [
                    'id' => $i->category->id,
                    'name_ru' => $i->category->getTranslation('name', 'ru'),
                    'name_en' => $i->category->getTranslation('name', 'en', false) ?: null,
                ],
            ]), 'about'),
            'languages' => Inertia::defer(fn () => $user->load('languages')->languages->pluck('language_code'), 'about'),
            'allTraits' => Inertia::defer(fn () => cache()->rememberForever('search_traits', fn () => PersonalityTrait::orderBy('sort_order')->get(['id', 'name'])->map(fn ($t) => [
                'id' => $t->id,
                'name_ru' => $t->getTranslation('name', 'ru'),
                'name_en' => $t->getTranslation('name', 'en', false) ?: null,
            ])), 'about'),
            'allCategories' => Inertia::defer(fn () => cache()->rememberForever('search_interest_categories', fn () => InterestCategory::with(['interests' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get()->map(fn ($cat) => [
                'id' => $cat->id,
                'name_ru' => $cat->getTranslation('name', 'ru'),
                'name_en' => $cat->getTranslation('name', 'en', false) ?: null,
                'interests' => $cat->interests->map(fn ($i) => [
                    'id' => $i->id,
                    'name_ru' => $i->getTranslation('name', 'ru'),
                    'name_en' => $i->getTranslation('name', 'en', false) ?: null,
                ])->values(),
            ])), 'about'),

            // Deferred group "services"
            'services' => Inertia::defer(function () use ($user) {
                $authId = auth()->id();
                $isOwner = $authId === $user->id;

                // All active categories
                $allCategories = cache()->rememberForever('active_service_categories', fn () => ServiceCategory::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'description', 'image_path', 'accent_color', 'sort_order', 'is_active'])
                );

                $hasUsedTrial = false;
                if ($authId && ! $isOwner) {
                    $hasUsedTrial = \App\Models\UserIdolTrial::where('user_id', $authId)
                        ->where('idol_id', $user->id)
                        ->exists();
                }

                // Services for this user
                $query = $user->services()->with(['timeUnit:id,name', 'latestReview', 'pendingChangeRequest.pendingCategory', 'pendingChangeRequest.pendingTimeUnit']);
                if (! $isOwner) {
                    $query->where('is_active', true)->where('status', 'approved');
                }
                $services = $query->orderBy('created_at')->get();

                // Idol descriptions
                $categoryIds = $allCategories->pluck('id');
                $descriptions = IdolCategoryDescription::where('user_id', $user->id)
                    ->whereIn('category_id', $categoryIds)
                    ->pluck('description', 'category_id');

                $servicesByCategory = $services->groupBy('category_id');

                return $allCategories->map(function ($cat) use ($servicesByCategory, $descriptions, $isOwner, $hasUsedTrial) {
                    $group = $servicesByCategory->get($cat->id, collect());

                    return [
                        'category' => [
                            'id' => $cat->id,
                            'name' => $cat->getTranslation('name', 'ru'),
                            'name_ru' => $cat->getTranslation('name', 'ru'),
                            'name_en' => $cat->getTranslation('name', 'en', false) ?: null,
                            'description_ru' => $cat->getTranslation('description', 'ru', false) ?: null,
                            'description_en' => $cat->getTranslation('description', 'en', false) ?: null,
                            'image_url' => $cat->image_path ? Storage::url($cat->image_path) : null,
                            'accent_color' => $cat->accent_color,
                            'sort_order' => $cat->sort_order,
                        ],
                        'idol_description' => $descriptions[$cat->id] ?? null,
                        'items' => $group->map(fn (Service $s) => (new ServiceResource($s, $isOwner, $hasUsedTrial))->resolve())->values(),
                    ];
                })->values();
            }, 'services'),
            'serviceCategories' => Inertia::defer(
                fn () => cache()->rememberForever('profile_service_categories', fn () => ServiceCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'name_suggestions', 'accent_color'])->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->getTranslation('name', 'ru'),
                    'name_ru' => $c->getTranslation('name', 'ru'),
                    'name_en' => $c->getTranslation('name', 'en', false) ?: null,
                    'name_suggestions' => $c->name_suggestions,
                    'accent_color' => $c->accent_color,
                ])),
                'services'
            ),
            'serviceTimeUnits' => Inertia::defer(
                fn () => cache()->rememberForever('search_service_time_units', fn () => ServiceTimeUnit::where('is_active', true)->orderBy('sort_order')->get()->map(fn ($u) => [
                    'id' => $u->id,
                    'name_ru' => $u->getTranslation('name', 'ru'),
                    'name_en' => $u->getTranslation('name', 'en', false) ?: null,
                ])),
                'services'
            ),

            // Deferred group "content" — content packs
            'contentPacks' => Inertia::defer(function () use ($user) {
                $authUser = auth()->user();
                $isOwner = $authUser && $authUser->id === $user->id;

                if ($isOwner) {
                    return ContentPack::where('user_id', $user->id)
                        ->with(['photos', 'latestReview', 'pendingChangeRequest'])
                        ->latest()
                        ->get()
                        ->map(fn (ContentPack $p) => (new ContentPackResource($p, true))->resolve())
                        ->values();
                }

                return ContentPack::where('user_id', $user->id)
                    ->where('status', 'published')
                    ->whereNull('hidden_at')
                    ->with(['photos'])
                    ->latest('published_at')
                    ->get()
                    ->map(function (ContentPack $p) use ($user) {
                        $p->idol_id = $user->id;
                        $p->idol_name = $user->name;

                        return (new ContentPackResource($p, false))->resolve();
                    })
                    ->values();
            }, 'content'),

            'purchasedPackIds' => Inertia::defer(function () use ($user) {
                $authUser = auth()->user();
                if (! $authUser || $authUser->id === $user->id) {
                    return [];
                }

                return ContentPackPurchase::where('user_id', $authUser->id)
                    ->whereHas('contentPack', fn ($q) => $q->where('user_id', $user->id))
                    ->pluck('content_pack_id')
                    ->toArray();
            }, 'content'),

        ]);
    }

    public function categoryIdols(User $user, ServiceCategory $category, Request $request): JsonResponse
    {
        $result = $this->profileService->getCategoryIdols($user, $category, $request);

        return response()->json($result);
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
        if (! empty($data['languages'])) {
            $now = now();
            $rows = array_map(fn ($code) => [
                'user_id' => $user->id,
                'language_code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_unique($data['languages']));
            UserLanguage::insert($rows);
        }

        return back();
    }

    public function updateHeader(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^\p{L}+(\s\p{L}+)?$/u', \Illuminate\Validation\Rule::unique('users', 'name')->ignore($request->user()->id)],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date', 'before:'.now()->subYears(18)->toDateString()],
            'timezone' => ['nullable', 'string', 'max:60', 'timezone:all'],
        ], [
            'name.required' => 'Имя обязательно.',
            'name.min' => 'Имя слишком короткое.',
            'name.max' => 'Имя слишком длинное.',
            'name.regex' => 'Имя должно содержать одно или два слова (только буквы).',
            'name.unique' => 'Этот логин уже занят.',
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
            'birth_date' => ['required', 'date', 'before:'.now()->subYears(18)->toDateString()],
        ]);
        $request->user()->update($data);

        return back();
    }

    public function updateVoice(Request $request): RedirectResponse
    {
        $request->validate(['voice' => ['required', 'file', 'mimes:webm,mp4,ogg', 'max:5120']]);
        $user = $request->user();
        $ext = $request->file('voice')->getClientOriginalExtension() ?: 'webm';
        $path = $request->file('voice')->storeAs('voices', "{$user->id}.{$ext}");
        $user->update(['voice_path' => $path]);

        return back();
    }

    public function deleteVoice(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->voice_path) {
            Storage::disk(config('filesystems.default'))->delete($user->voice_path);
            $user->update(['voice_path' => null]);
        }

        return back();
    }

    public function updateChecklist(Request $request): RedirectResponse
    {
        $data = $request->validate(['snooze' => ['required', 'in:day,week,forever']]);
        $until = match ($data['snooze']) {
            'day' => now()->addDay(),
            'week' => now()->addWeek(),
            'forever' => now()->addYears(100),
        };
        $request->user()->update(['profile_checklist_snoozed_until' => $until]);

        return back();
    }

    public function updateAvatar(\App\Http\Requests\Profile\UpdateAvatarRequest $request, \App\Services\Media\AvatarService $avatarService): RedirectResponse
    {
        try {
            $avatarService->updateAvatar($request->user(), $request->file('avatar'));
        } catch (\Throwable $e) {
            return back()->withErrors(['avatar' => $e->getMessage()]);
        }

        return back();
    }

    public function deleteAvatar(Request $request, \App\Services\Media\AvatarService $avatarService): RedirectResponse
    {
        $avatarService->deleteAvatar($request->user());

        return back();
    }

    public function updateCategoryDescription(Request $request, ServiceCategory $category): RedirectResponse
    {
        abort_unless($request->user()->is_idol, 403);

        $data = $request->validate(['description' => ['nullable', 'string', 'max:1000']]);

        IdolCategoryDescription::updateOrCreate(
            ['user_id' => $request->user()->id, 'category_id' => $category->id],
            ['description' => $data['description']],
        );

        return back();
    }

    public function storePost(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->is_idol, 403, 'Только айдолы могут создавать публикации.');

        $request->validate([
            'body' => ['required', 'string', 'max:377'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $this->profileService->createPost($user, ['body' => $request->input('body')], $request->file('photo'));

        return back();
    }

    public function destroyPost(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);
        $this->profileService->deletePost($post);

        return back();
    }

    public function getPosts(Request $request, User $user): JsonResponse
    {
        $paginated = $user->posts()
            ->with(['user:id,name,avatar_path,active_frame_id,gender', 'user.activeFrame'])
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
            'id' => $p->id,
            'body' => $p->body,
            'photo_url' => $p->photo_url,
            'created_at' => $p->created_at->translatedFormat('d M Y'),
            'likes_count' => $p->likes_count,
            'liked_by_me' => isset($likedIds[$p->id]),
            'comments_count' => $p->comments_count,
            'author' => [
                'id' => $p->user->id,
                'name' => $p->user->name,
                'avatar_url' => $p->user->avatar_url,
                'gender' => $p->user->gender,
            ],
        ]);

        return response()->json([
            'data' => $data,
            'next_page_url' => $paginated->nextPageUrl(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    public function getComments(Request $request, Post $post): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        $paginator = $post->rootComments()
            ->with(['user:id,name,avatar_path,active_frame_id,gender', 'user.activeFrame'])
            ->withCount('replies')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $comments = $paginator->getCollection();

        // For comments with exactly 1 reply, load it inline so it displays without a toggle button
        $singleIds = $comments->filter(fn ($c) => $c->replies_count === 1)->pluck('id');
        if ($singleIds->isNotEmpty()) {
            $singleReplies = PostComment::with(['user:id,name,avatar_path,active_frame_id,gender', 'user.activeFrame'])
                ->whereIn('parent_id', $singleIds)
                ->orderBy('created_at')
                ->get()
                ->groupBy('parent_id');

            $comments->each(function ($comment) use ($singleReplies) {
                if ($comment->replies_count === 1) {
                    $comment->setRelation('replies', $singleReplies->get($comment->id, collect()));
                }
            });
        }

        $mapReply = fn (PostComment $r) => [
            'id' => $r->id,
            'body' => $r->body,
            'created_at' => $r->created_at->diffForHumans(),
            'user' => [
                'id' => $r->user->id,
                'name' => $r->user->name,
                'avatar_url' => $r->user->avatar_url,
                'gender' => $r->user->gender,
            ],
        ];

        $data = $comments->map(fn (PostComment $c) => [
            'id' => $c->id,
            'body' => $c->body,
            'created_at' => $c->created_at->diffForHumans(),
            'user' => [
                'id' => $c->user->id,
                'name' => $c->user->name,
                'avatar_url' => $c->user->avatar_url,
                'gender' => $c->user->gender,
            ],
            'replies_count' => $c->replies_count,
            'replies' => $c->replies_count === 1
                ? $c->replies->map($mapReply)->values()
                : [],
        ]);

        return response()->json([
            'data' => $data,
            'has_more' => $paginator->hasMorePages(),
            'total' => $paginator->total(),
        ]);
    }

    public function getReplies(Request $request, PostComment $comment): JsonResponse
    {
        abort_if($comment->parent_id !== null, 422, 'Cannot get replies of a reply.');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $paginator = PostComment::where('parent_id', $comment->id)
            ->with(['user:id,name,avatar_path,active_frame_id,gender', 'user.activeFrame'])
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->map(fn (PostComment $r) => [
                'id' => $r->id,
                'body' => $r->body,
                'created_at' => $r->created_at->diffForHumans(),
                'user' => [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'avatar_url' => $r->user->avatar_url,
                    'gender' => $r->user->gender,
                ],
            ])->values(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
            'next_page' => $paginator->hasMorePages() ? $page + 1 : null,
        ]);
    }

    public function toggleLike(Request $request, Post $post): JsonResponse
    {
        $postOwner = User::select(['id', 'is_banned', 'banned_until'])->find($post->user_id);
        abort_if($postOwner && $postOwner->isActiveBanned(), 422, 'user_banned');

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
            'liked' => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function storeComment(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:177'],
            'parent_id' => ['nullable', 'integer', 'exists:post_comments,id'],
        ]);

        $postOwner = User::select(['id', 'is_banned', 'banned_until'])->find($post->user_id);
        abort_if($postOwner && $postOwner->isActiveBanned(), 422, 'user_banned');

        if (! empty($data['parent_id'])) {
            $parent = PostComment::findOrFail($data['parent_id']);
            abort_if($parent->post_id !== $post->id, 422, 'Parent comment does not belong to this post.');
            abort_if($parent->parent_id !== null, 422, 'Cannot reply to a reply.');
        }

        $comment = $this->profileService->createComment($request->user(), $post, $data);
        $comment->load(['user:id,name,avatar_path,active_frame_id', 'user.activeFrame']);

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at->diffForHumans(),
            'user' => ['id' => $comment->user->id, 'name' => $comment->user->name, 'avatar_url' => $comment->user->avatar_url],
            'replies_count' => 0,
            'replies' => [],
        ]);
    }

    public function destroyComment(Request $request, PostComment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return response()->json(['deleted' => true]);
    }

    public function storeInterestSuggestion(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $userId = $request->user()->id;
        $name = $data['name'];
        $exists = InterestSuggestion::where('user_id', $userId)->where('name', $name)->exists();
        if (! $exists) {
            InterestSuggestion::create(['user_id' => $userId, 'name' => $name, 'status' => 'pending']);
        }

        return back();
    }

    public function storeTraitSuggestion(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $userId = $request->user()->id;
        $name = $data['name'];

        $exists = TraitSuggestion::where('user_id', $userId)->where('name', $name)->exists();
        if (! $exists) {
            TraitSuggestion::create(['user_id' => $userId, 'name' => $name, 'status' => 'pending']);
        }

        return back();
    }

    public function toggleDisallowIdolMessages(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->is_idol, 403, 'Idols cannot toggle this setting.');

        $newValue = $request->has('disallow')
            ? $request->boolean('disallow')
            : ! $user->disallow_idol_messages;

        $user->update(['disallow_idol_messages' => $newValue]);

        return response()->json([
            'disallow_idol_messages' => (bool) $user->disallow_idol_messages,
        ]);
    }
}
