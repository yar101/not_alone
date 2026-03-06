<?php

namespace App\Http\Controllers;

use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\PersonalityTrait;
use App\Models\Post;
use App\Models\User;
use App\Models\UserLanguage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    public function show(User $user): Response
    {
        $user->load(['traits', 'interests.category', 'languages', 'posts' => fn ($q) => $q->latest()]);

        $checklistSnoozed = false;
        if ($user->profile_checklist_snoozed_until !== null) {
            $checklistSnoozed = $user->profile_checklist_snoozed_until->isFuture();
        }

        $allTraits = PersonalityTrait::orderBy('sort_order')->get(['id', 'name_ru']);
        $allCategories = InterestCategory::with(['interests' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Profile/Show', [
            'allTraits'    => $allTraits,
            'allCategories' => $allCategories,
            'profileUser' => [
                'id'               => $user->id,
                'name'             => $user->name,
                'gender'           => $user->gender,
                'age'              => $user->age,
                'birth_date'       => $user->birth_date?->toDateString(),
                'about'            => $user->about,
                'voice_url'        => $user->voice_path ? Storage::url($user->voice_path) : null,
                'avatar_url'       => $user->avatar_url,
                'pinned_body'      => $user->pinned_body,
                'pinned_photo_url' => $user->pinned_photo_url,
                'timezone'         => $user->timezone,
                'traits'           => $user->traits->map(fn ($t) => ['id' => $t->id, 'name_ru' => $t->name_ru]),
                'interests'        => $user->interests->map(fn ($i) => [
                    'id'       => $i->id,
                    'name_ru'  => $i->name_ru,
                    'category' => ['id' => $i->category->id, 'name_ru' => $i->category->name_ru],
                ]),
                'languages'        => $user->languages->pluck('language_code'),
                'checklist_snoozed' => $checklistSnoozed,
                'posts'            => $user->posts->map(fn ($p) => [
                    'id'         => $p->id,
                    'body'       => $p->body,
                    'photo_url'  => $p->photo_url,
                    'created_at' => $p->created_at->translatedFormat('d M Y'),
                ]),
            ],
            'isOwner' => auth()->id() === $user->id,
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

    public function updatePinnedCard(Request $request): RedirectResponse
    {
        $request->validate([
            'body'  => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $user = $request->user();
        $body = $request->input('body');
        $data = ['pinned_body' => $body ? strip_tags($body, '<p><br><strong><em><u><s><ul><ol><li>') : null];
        if ($request->hasFile('photo')) {
            if ($user->pinned_photo_path) {
                Storage::disk('public')->delete($user->pinned_photo_path);
            }
            $ext  = $request->file('photo')->getClientOriginalExtension() ?: 'jpg';
            $path = $request->file('photo')->storeAs('pinned', "{$user->id}.{$ext}", 'public');
            $data['pinned_photo_path'] = $path;
        }
        $user->update($data);
        return back();
    }

    public function deletePinnedPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->pinned_photo_path) {
            Storage::disk('public')->delete($user->pinned_photo_path);
            $user->update(['pinned_photo_path' => null]);
        }
        return back();
    }

    public function storePost(Request $request): RedirectResponse
    {
        $request->validate([
            'body'  => ['required', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
}
