<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Events\NewNotification;
use App\Models\ContentPack;
use App\Notifications\ContentPackApprovedNotification;
use App\Notifications\ContentPackRejectedNotification;
use App\Notifications\ContentPackRemarksNotification;
use Illuminate\Support\Facades\Storage;
use App\Services\AdminLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentPackModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'pending_review');
        $search = $request->input('search');

        $query = ContentPack::with(['user', 'photos', 'latestReview'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(fn ($q2) =>
                $q2->where('title', 'like', "%{$search}%")
                   ->orWhereHas('user', fn ($u) =>
                       $u->where('name', 'like', "%{$search}%"))
            ));

        $packs = $query->latest()->paginate(50)->through(fn (ContentPack $p) => [
            'id'         => $p->id,
            'title'      => $p->title,
            'price'      => $p->price,
            'status'     => $p->status,
            'cover_url'       => $p->cover_url,
            'created_at'      => $p->created_at,
            'resubmitted_at'  => $p->resubmitted_at?->toIso8601String(),
            'user'            => $p->user ? [
                'id'         => $p->user->id,
                'name'       => $p->user->name,
                'avatar_url' => $p->user->avatar_url,
            ] : null,
        ]);

        $counts = [
            'pending_review' => ContentPack::where('status', 'pending_review')->count(),
            'approved'       => ContentPack::where('status', 'approved')->count(),
            'published'      => ContentPack::where('status', 'published')->count(),
            'has_remarks'    => ContentPack::where('status', 'has_remarks')->count(),
            'resubmitted'    => ContentPack::where('status', 'pending_review')->whereNotNull('resubmitted_at')->count(),
        ];

        return Inertia::render('Admin/ContentPacks/Index', [
            'packs'   => $packs,
            'filters' => ['status' => $status, 'search' => $search],
            'counts'  => $counts,
        ]);
    }

    public function show(ContentPack $pack): Response
    {
        $pack->load(['user', 'photos', 'reviews.admin']);

        return Inertia::render('Admin/ContentPacks/Show', [
            'pack' => [
                'id'          => $pack->id,
                'title'       => $pack->title,
                'description' => $pack->description,
                'price'       => $pack->price,
                'status'      => $pack->status,
                'cover_url'   => $pack->cover_url,
                'cover_path'  => $pack->cover_path,
                'created_at'  => $pack->created_at,
                'user'        => [
                    'id'         => $pack->user->id,
                    'name'       => $pack->user->name,
                    'avatar_url' => $pack->user->avatar_url,
                    'email'      => $pack->user->email,
                ],
                'photos' => $pack->photos->map(fn ($ph) => [
                    'id'  => $ph->id,
                    'url' => $ph->url,
                ])->values(),
                'reviews' => $pack->reviews->map(fn ($r) => [
                    'id'               => $r->id,
                    'decision'         => $r->decision,
                    'flagged_fields'    => $r->flagged_fields ?? [],
                    'field_comments'    => $r->field_comments ?? [],
                    'flagged_photo_ids' => $r->flagged_photo_ids ?? [],
                    'photo_comments'    => $r->photo_comments ?? [],
                    'created_at'        => $r->created_at,
                    'admin'            => $r->admin ? ['name' => $r->admin->name] : null,
                ])->values(),
            ],
        ]);
    }

    public function decide(Request $request, ContentPack $pack): RedirectResponse
    {
        // field_comments and photo_comments arrive as JSON strings from FormData
        $request->merge([
            'field_comments' => is_string($request->input('field_comments'))
                ? json_decode($request->input('field_comments'), true)
                : $request->input('field_comments'),
            'photo_comments' => is_string($request->input('photo_comments'))
                ? json_decode($request->input('photo_comments'), true)
                : $request->input('photo_comments'),
        ]);

        $data = $request->validate([
            'decision'            => ['required', 'in:approved,has_remarks,rejected'],
            'flagged_fields'      => ['nullable', 'array'],
            'flagged_fields.*'    => ['string', 'in:title,description,price'],
            'field_comments'      => ['nullable', 'array'],
            'flagged_photo_ids'   => ['nullable', 'array'],
            'flagged_photo_ids.*' => ['integer'],
            'photo_comments'      => ['nullable', 'array'],
        ]);

        $admin = auth('admin')->user();

        $pack->reviews()->create([
            'admin_id'          => $admin->id,
            'decision'          => $data['decision'],
            'flagged_fields'    => $data['flagged_fields'] ?? null,
            'field_comments'    => $data['field_comments'] ?? null,
            'flagged_photo_ids' => $data['flagged_photo_ids'] ?? null,
            'photo_comments'    => $data['photo_comments'] ?? null,
        ]);

        if ($data['decision'] === 'approved') {
            $pack->update([
                'status'          => 'approved',
                'moderated_by'    => $admin->id,
                'moderated_at'    => now(),
                'resubmitted_at'  => null,
            ]);

            $pack->user->notify(new ContentPackApprovedNotification($pack));
            broadcast(new NewNotification('private', $pack->user->id));
        } elseif ($data['decision'] === 'rejected') {
            foreach ($pack->photos as $photo) {
                Storage::disk('public')->delete($photo->path);
            }
            Storage::disk('public')->deleteDirectory('content-packs/' . $pack->id);
            $pack->photos()->delete();

            $pack->update([
                'status'         => 'rejected',
                'cover_path'     => null,
                'resubmitted_at' => null,
                'moderated_by'   => $admin->id,
                'moderated_at'   => now(),
            ]);

            $pack->user->notify(new ContentPackRejectedNotification($pack));
            broadcast(new NewNotification('private', $pack->user->id));
        } else {
            $pack->update(['status' => 'has_remarks', 'resubmitted_at' => null]);
            $pack->user->notify(new ContentPackRemarksNotification($pack));
            broadcast(new NewNotification('private', $pack->user->id));
        }

        AdminLogService::log(
            $admin->id,
            'decide_content_pack',
            'content_pack',
            $pack->id,
            ['decision' => $data['decision']]
        );

        return back()->with('success', 'Решение отправлено.');
    }
}
