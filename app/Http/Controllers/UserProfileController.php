<?php

namespace App\Http\Controllers;

use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\IdolCategoryDescription;
use App\Models\InterestSuggestion;
use App\Models\TraitSuggestion;
use App\Models\InterestCategory;
use App\Models\PersonalityTrait;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Jobs\NotifyFollowersJob;

use App\Models\ChatBlock;
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
                'is_banned'        => $user->isActiveBanned(),
            ],
            'isOwner'          => auth()->id() === $user->id,
            'isIdol'           => (bool) $user->is_idol,
            'rating'           => $user->rating,
            'isFollowing'      => auth()->check() ? auth()->user()->isFollowing($user->id) : false,
            'isBlockedByIdol'  => auth()->check()
                ? ChatBlock::active()
                    ->where('blocker_id', $user->id)
                    ->where('blocked_id', auth()->id())
                    ->exists()
                : false,

            // Deferred group "about" — traits, interests, languages + their catalogs
            'traits'        => Inertia::defer(fn () => $user->load('traits')->traits->map(fn ($t) => [
                'id'      => $t->id,
                'name_ru' => $t->getTranslation('name', 'ru'),
                'name_en' => $t->getTranslation('name', 'en', false) ?: null,
            ]), 'about'),
            'interests'     => Inertia::defer(fn () => $user->load('interests.category')->interests->map(fn ($i) => [
                'id'       => $i->id,
                'name_ru'  => $i->getTranslation('name', 'ru'),
                'name_en'  => $i->getTranslation('name', 'en', false) ?: null,
                'category' => [
                    'id'      => $i->category->id,
                    'name_ru' => $i->category->getTranslation('name', 'ru'),
                    'name_en' => $i->category->getTranslation('name', 'en', false) ?: null,
                ],
            ]), 'about'),
            'languages'     => Inertia::defer(fn () => $user->load('languages')->languages->pluck('language_code'), 'about'),
            'allTraits'     => Inertia::defer(fn () => PersonalityTrait::orderBy('sort_order')->get(['id', 'name'])->map(fn ($t) => [
                'id'      => $t->id,
                'name_ru' => $t->getTranslation('name', 'ru'),
                'name_en' => $t->getTranslation('name', 'en', false) ?: null,
            ]), 'about'),
            'allCategories' => Inertia::defer(fn () => InterestCategory::with(['interests' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get()->map(fn ($cat) => [
                'id'        => $cat->id,
                'name_ru'   => $cat->getTranslation('name', 'ru'),
                'name_en'   => $cat->getTranslation('name', 'en', false) ?: null,
                'sort_order' => $cat->sort_order,
                'interests' => $cat->interests->map(fn ($i) => [
                    'id'      => $i->id,
                    'name_ru' => $i->getTranslation('name', 'ru'),
                    'name_en' => $i->getTranslation('name', 'en', false) ?: null,
                ])->values(),
            ]), 'about'),

            // Deferred group "services"
            'services'     => Inertia::defer(function () use ($user) {
                $authId  = auth()->id();
                $isOwner = $authId === $user->id;

                // All active categories
                $allCategories = ServiceCategory::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'description', 'image_path', 'accent_color', 'sort_order', 'is_active']);

                // Services for this user
                $query = $user->services()->with(['timeUnit:id,name', 'latestReview', 'pendingChangeRequest.pendingCategory', 'pendingChangeRequest.pendingTimeUnit']);
                if (!$isOwner) {
                    $query->where('is_active', true)->where('status', 'approved');
                }
                $services = $query->orderBy('created_at')->get();

                // Idol descriptions
                $categoryIds  = $allCategories->pluck('id');
                $descriptions = IdolCategoryDescription::where('user_id', $user->id)
                    ->whereIn('category_id', $categoryIds)
                    ->pluck('description', 'category_id');

                $servicesByCategory = $services->groupBy('category_id');

                return $allCategories->map(function ($cat) use ($servicesByCategory, $descriptions, $isOwner) {
                    $group = $servicesByCategory->get($cat->id, collect());
                    return [
                        'category' => [
                            'id'           => $cat->id,
                            'name'         => $cat->getTranslation('name', 'ru'),
                            'name_ru'      => $cat->getTranslation('name', 'ru'),
                            'name_en'      => $cat->getTranslation('name', 'en', false) ?: null,
                            'description_ru' => $cat->getTranslation('description', 'ru', false) ?: null,
                            'description_en' => $cat->getTranslation('description', 'en', false) ?: null,
                            'image_url'    => $cat->image_path ? Storage::url($cat->image_path) : null,
                            'accent_color' => $cat->accent_color,
                            'sort_order'   => $cat->sort_order,
                        ],
                        'idol_description' => $descriptions[$cat->id] ?? null,
                        'items'            => $group->map(function (Service $s) use ($isOwner) {
                            $base = [
                                'id'               => $s->id,
                                'name_ru'          => $s->getTranslation('name', 'ru'),
                                'name_en'          => $s->getTranslation('name', 'en', false) ?: null,
                                'price'            => $s->price,
                                'is_active'        => $s->is_active,
                                'status'           => $s->status,
                                'rejection_reason' => $s->rejection_reason,
                                'category_id'      => $s->category_id,
                                'time_unit'        => [
                                    'id'      => $s->timeUnit->id,
                                    'name_ru' => $s->timeUnit->getTranslation('name', 'ru'),
                                    'name_en' => $s->timeUnit->getTranslation('name', 'en', false) ?: null,
                                ],
                            ];

                            if ($isOwner) {
                                $base['latest_review'] = $s->latestReview ? [
                                    'decision'       => $s->latestReview->decision,
                                    'flagged_fields' => $s->latestReview->flagged_fields ?? [],
                                    'field_comments' => $s->latestReview->field_comments ?? [],
                                ] : null;

                                $base['pending_change'] = $s->pendingChangeRequest ? [
                                    'changed_fields'   => $s->pendingChangeRequest->changed_fields,
                                    'pending_name'     => $s->pendingChangeRequest->pending_name,
                                    'pending_price'    => $s->pendingChangeRequest->pending_price,
                                    'pending_category' => $s->pendingChangeRequest->pendingCategory ? [
                                        'id'      => $s->pendingChangeRequest->pendingCategory->id,
                                        'name_ru' => $s->pendingChangeRequest->pendingCategory->getTranslation('name', 'ru'),
                                    ] : null,
                                    'pending_time_unit' => $s->pendingChangeRequest->pendingTimeUnit ? [
                                        'id'      => $s->pendingChangeRequest->pendingTimeUnit->id,
                                        'name_ru' => $s->pendingChangeRequest->pendingTimeUnit->getTranslation('name', 'ru'),
                                    ] : null,
                                    'status'           => $s->pendingChangeRequest->status,
                                    'flagged_fields'   => $s->pendingChangeRequest->flagged_fields ?? [],
                                    'field_comments'   => $s->pendingChangeRequest->field_comments ?? [],
                                    'admin_comment'    => $s->pendingChangeRequest->admin_comment,
                                ] : null;
                            }

                            return $base;
                        })->values(),
                    ];
                })->values();
            }, 'services'),
            'serviceCategories' => Inertia::defer(
                fn () => ServiceCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'name_suggestions', 'accent_color'])->map(fn ($c) => [
                    'id'             => $c->id,
                    'name'           => $c->getTranslation('name', 'ru'),
                    'name_ru'        => $c->getTranslation('name', 'ru'),
                    'name_en'        => $c->getTranslation('name', 'en', false) ?: null,
                    'name_suggestions' => $c->name_suggestions,
                    'accent_color'   => $c->accent_color,
                ]),
                'services'
            ),
            'serviceTimeUnits' => Inertia::defer(
                fn () => ServiceTimeUnit::where('is_active', true)->orderBy('sort_order')->get()->map(fn ($u) => [
                    'id'      => $u->id,
                    'name_ru' => $u->getTranslation('name', 'ru'),
                    'name_en' => $u->getTranslation('name', 'en', false) ?: null,
                ]),
                'services'
            ),

            // Deferred group "content" — content packs
            'contentPacks' => Inertia::defer(function () use ($user) {
                $authUser = auth()->user();
                $isOwner  = $authUser && $authUser->id === $user->id;

                if ($isOwner) {
                    return ContentPack::where('user_id', $user->id)
                        ->with(['photos', 'latestReview', 'pendingChangeRequest'])
                        ->latest()
                        ->get()
                        ->map(fn (ContentPack $p) => [
                            'id'          => $p->id,
                            'title'       => $p->title,
                            'description' => $p->description,
                            'price'       => $p->price,
                            'status'      => $p->status,
                            'cover_url'   => $p->cover_url,
                            'photos_count' => $p->photos->count(),
                            'published_at' => $p->published_at?->toIso8601String(),
                            'hidden_at'    => $p->hidden_at?->toIso8601String(),
                            'latest_review' => $p->latestReview ? [
                                'decision'          => $p->latestReview->decision,
                                'flagged_fields'    => $p->latestReview->flagged_fields ?? [],
                                'field_comments'    => $p->latestReview->field_comments ?? [],
                                'flagged_photo_ids' => $p->latestReview->flagged_photo_ids ?? [],
                                'photo_comments'    => $p->latestReview->photo_comments ?? [],
                            ] : null,
                            'photos' => $p->photos->map(fn ($ph) => [
                                'id'  => $ph->id,
                                'url' => $ph->url,
                            ])->values(),
                            'pending_change' => $p->pendingChangeRequest ? [
                                'changed_fields'      => $p->pendingChangeRequest->changed_fields,
                                'pending_title'       => $p->pendingChangeRequest->pending_title,
                                'pending_description' => $p->pendingChangeRequest->pending_description,
                                'pending_price'       => $p->pendingChangeRequest->pending_price,
                                'status'              => $p->pendingChangeRequest->status,
                                'flagged_fields'      => $p->pendingChangeRequest->flagged_fields ?? [],
                                'field_comments'      => $p->pendingChangeRequest->field_comments ?? [],
                                'admin_comment'       => $p->pendingChangeRequest->admin_comment,
                            ] : null,
                        ])
                        ->values();
                }

                return ContentPack::where('user_id', $user->id)
                    ->where('status', 'published')
                    ->whereNull('hidden_at')
                    ->with(['photos'])
                    ->latest('published_at')
                    ->get()
                    ->map(fn (ContentPack $p) => [
                        'id'          => $p->id,
                        'title'       => $p->title,
                        'description' => $p->description,
                        'price'       => $p->price,
                        'status'      => $p->status,
                        'cover_url'   => $p->cover_url,
                        'photos_count' => $p->photos->count(),
                        'published_at' => $p->published_at?->toIso8601String(),
                        'idol_id'     => $user->id,
                        'idol_name'   => $user->name,
                    ])
                    ->values();
            }, 'content'),

            'purchasedPackIds' => Inertia::defer(function () use ($user) {
                $authUser = auth()->user();
                if (!$authUser || $authUser->id === $user->id) {
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
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = min(8, max(1, (int) $request->get('per_page', 4)));

        $idols = Service::where('is_active', true)
            ->where('status', 'approved')
            ->where('user_id', '!=', $user->id)
            ->where('category_id', $category->id)
            ->with(['user:id,name,avatar_path,rating'])
            ->get(['id', 'user_id'])
            ->unique('user_id');

        // Стабильная рандомизация: seed из сессии, одинаковый на всех страницах пагинации
        $seedKey = 'idol_shuffle_' . $user->id . '_' . $category->id;
        $seed = $request->session()->get($seedKey);
        if (!$seed || $page === 1) {
            $seed = mt_rand();
            $request->session()->put($seedKey, $seed);
        }
        mt_srand($seed);
        $idols = $idols->shuffle();

        $total = $idols->count();
        $paged = $idols->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'idols'   => $paged->map(fn ($s) => [
                'id'         => $s->user->id,
                'name'       => $s->user->name,
                'avatar_url' => $s->user->avatar_url,
                'rating'     => $s->user->rating,
            ])->values(),
            'total'   => $total,
            'page'    => $page,
            'hasMore' => ($page * $perPage) < $total,
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
            'name'       => ['required', 'string', 'min:2', 'max:100', 'regex:/^\p{L}+(\s\p{L}+)?$/u', \Illuminate\Validation\Rule::unique('users', 'name')->ignore($request->user()->id)],
            'gender'     => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date', 'before:' . now()->subYears(18)->toDateString()],
            'timezone'   => ['nullable', 'string', 'max:60', 'timezone:all'],
        ], [
            'name.required' => 'Имя обязательно.',
            'name.min'      => 'Имя слишком короткое.',
            'name.max'      => 'Имя слишком длинное.',
            'name.regex'    => 'Имя должно содержать одно или два слова (только буквы).',
            'name.unique'   => 'Этот логин уже занят.',
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
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $file = $request->file('avatar');
        $realPath = $file->getRealPath();
        $img = imagecreatefromstring(file_get_contents($realPath));

        if (!$img) {
            return back()->withErrors(['avatar' => 'Could not process image.']);
        }

        // Fix orientation from EXIF
        $exif = @exif_read_data($realPath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $img = imagerotate($img, 180, 0); break;
                case 6: $img = imagerotate($img, -90, 0); break;
                case 8: $img = imagerotate($img, 90, 0); break;
            }
        }

        $width  = imagesx($img);
        $height = imagesy($img);
        $targetSize = 600;

        $newImg = imagecreatetruecolor($targetSize, $targetSize);

        // Preserve transparency or fill with white for JPEGs
        $white = imagecolorallocate($newImg, 255, 255, 255);
        imagefill($newImg, 0, 0, $white);

        // Calculate crop to maintain square aspect ratio (center crop)
        if ($width > $height) {
            $srcX = (int)(($width - $height) / 2);
            $srcY = 0;
            $srcW = $height;
            $srcH = $height;
        } else {
            $srcX = 0;
            $srcY = (int)(($height - $width) / 2);
            $srcW = $width;
            $srcH = $width;
        }

        imagecopyresampled($newImg, $img, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $srcW, $srcH);

        $path = "avatars/{$user->id}_" . time() . ".jpg";
        
        ob_start();
        imagejpeg($newImg, null, 70);
        $imageData = ob_get_clean();
        
        Storage::disk('public')->put($path, $imageData);

        imagedestroy($img);
        imagedestroy($newImg);

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
        $user = $request->user();
        abort_unless($user->is_idol, 403, 'Только айдолы могут создавать публикации.');

        $request->validate([
            'body'  => ['required', 'string', 'max:377'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $user = $request->user();
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $realPath = $file->getRealPath();
            $img = imagecreatefromstring(file_get_contents($realPath));

            if ($img) {
                // Fix orientation from EXIF
                $exif = @exif_read_data($realPath);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3: $img = imagerotate($img, 180, 0); break;
                        case 6: $img = imagerotate($img, -90, 0); break;
                        case 8: $img = imagerotate($img, 90, 0); break;
                    }
                }

                $width  = imagesx($img);
                $height = imagesy($img);
                $maxDim = 1600;

                if ($width > $maxDim || $height > $maxDim) {
                    $ratio = $width / $height;
                    if ($ratio > 1) {
                        $newWidth  = $maxDim;
                        $newHeight = (int)($maxDim / $ratio);
                    } else {
                        $newHeight = $maxDim;
                        $newWidth  = (int)($maxDim * $ratio);
                    }
                } else {
                    $newWidth  = $width;
                    $newHeight = $height;
                }

                $newImg = imagecreatetruecolor($newWidth, $newHeight);
                $white  = imagecolorallocate($newImg, 255, 255, 255);
                imagefill($newImg, 0, 0, $white);
                imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                $photoPath = "posts/{$user->id}/" . time() . ".jpg";
                ob_start();
                imagejpeg($newImg, null, 70);
                $imageData = ob_get_clean();

                Storage::disk('public')->put($photoPath, $imageData);
                imagedestroy($img);
                imagedestroy($newImg);
            } else {
                // Fallback to regular store if GD fails
                $ext       = $file->getClientOriginalExtension() ?: 'jpg';
                $photoPath = $file->storeAs("posts/{$user->id}", time() . '.' . $ext, 'public');
            }
        }
        $post = Post::create([
            'user_id'    => $user->id,
            'body'       => $request->input('body'),
            'photo_path' => $photoPath,
        ]);

        NotifyFollowersJob::dispatch($user, $post);

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
            ->with('user:id,name,avatar_path,gender')
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
                'gender'     => $p->user->gender,
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
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        $paginator = $post->comments()
            ->with(['user:id,name,avatar_path,gender'])
            ->withCount('replies')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $comments = $paginator->getCollection();

        // For comments with exactly 1 reply, load it inline so it displays without a toggle button
        $singleIds = $comments->filter(fn ($c) => $c->replies_count === 1)->pluck('id');
        if ($singleIds->isNotEmpty()) {
            $singleReplies = PostComment::with('user:id,name,avatar_path,gender')
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
            'id'         => $r->id,
            'body'       => $r->body,
            'created_at' => $r->created_at->diffForHumans(),
            'user'       => [
                'id'         => $r->user->id,
                'name'       => $r->user->name,
                'avatar_url' => $r->user->avatar_url,
                'gender'     => $r->user->gender,
            ],
        ];

        $data = $comments->map(fn (PostComment $c) => [
            'id'            => $c->id,
            'body'          => $c->body,
            'created_at'    => $c->created_at->diffForHumans(),
            'user'          => [
                'id'         => $c->user->id,
                'name'       => $c->user->name,
                'avatar_url' => $c->user->avatar_url,
                'gender'     => $c->user->gender,
            ],
            'replies_count' => $c->replies_count,
            'replies'       => $c->replies_count === 1
                ? $c->replies->map($mapReply)->values()
                : [],
        ]);

        return response()->json([
            'data'     => $data,
            'has_more' => $paginator->hasMorePages(),
            'total'    => $paginator->total(),
        ]);
    }

    public function getReplies(Request $request, PostComment $comment): JsonResponse
    {
        abort_if($comment->parent_id !== null, 422, 'Cannot get replies of a reply.');

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $paginator = PostComment::where('parent_id', $comment->id)
            ->with('user:id,name,avatar_path,gender')
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data'      => $paginator->map(fn (PostComment $r) => [
                'id'         => $r->id,
                'body'       => $r->body,
                'created_at' => $r->created_at->diffForHumans(),
                'user'       => [
                    'id'         => $r->user->id,
                    'name'       => $r->user->name,
                    'avatar_url' => $r->user->avatar_url,
                    'gender'     => $r->user->gender,
                ],
            ])->values(),
            'total'     => $paginator->total(),
            'has_more'  => $paginator->hasMorePages(),
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

        $postOwner = User::select(['id', 'is_banned', 'banned_until'])->find($post->user_id);
        abort_if($postOwner && $postOwner->isActiveBanned(), 422, 'user_banned');

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
            'id'            => $comment->id,
            'body'          => $comment->body,
            'created_at'    => $comment->created_at->diffForHumans(),
            'user'          => ['id' => $comment->user->id, 'name' => $comment->user->name, 'avatar_url' => $comment->user->avatar_url],
            'replies_count' => 0,
            'replies'       => [],
        ]);
    }

    public function destroyComment(Request $request, PostComment $comment): JsonResponse
    {
        abort_if($comment->user_id !== $request->user()->id, 403);
        $comment->delete();
        return response()->json(['deleted' => true]);
    }

    public function storeInterestSuggestion(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $userId = $request->user()->id;
        $name = $data['name'];
        $exists = InterestSuggestion::where('user_id', $userId)->where('name', $name)->exists();
        if (!$exists) {
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
        if (!$exists) {
            TraitSuggestion::create(['user_id' => $userId, 'name' => $name, 'status' => 'pending']);
        }

        return back();
    }
}
