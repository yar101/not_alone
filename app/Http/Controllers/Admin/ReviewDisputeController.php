<?php

namespace App\Http\Controllers\Admin;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\ReviewDispute;
use App\Notifications\ReviewDisputeApprovedNotification;
use App\Notifications\ReviewDisputeRejectedNotification;
use App\Services\AdminLogService;
use App\Services\IdolRatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewDisputeController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->get('status', 'pending');
        $search = $request->get('search', '');

        $query = ReviewDispute::with([
            'review.reviewer:id,name,avatar_path',
            'idol:id,name,avatar_path',
        ])->latest('created_at');

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($search) {
            $query->whereHas('idol', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
        }

        $disputes = $query->paginate(20)->through(fn (ReviewDispute $d) => $this->format($d));

        $rawCounts = ReviewDispute::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('Admin/ReviewDisputes/Index', [
            'disputes' => $disputes,
            'status_filter' => $statusFilter,
            'search' => $search,
            'counts' => [
                'pending' => (int) ($rawCounts['pending'] ?? 0),
                'approved' => (int) ($rawCounts['approved'] ?? 0),
                'rejected' => (int) ($rawCounts['rejected'] ?? 0),
            ],
        ]);
    }

    public function resolve(Request $request, ReviewDispute $reviewDispute): RedirectResponse
    {
        $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($reviewDispute->status !== 'pending') {
            return back()->withErrors(['dispute' => 'Жалоба уже рассмотрена.']);
        }

        $adminId = auth('admin')->id();
        $idol = $reviewDispute->idol;

        $reviewDispute->update([
            'status' => $request->decision,
            'admin_note' => $request->admin_note,
            'resolved_at' => now(),
        ]);

        if ($request->decision === 'approved') {
            $reviewDispute->review->update(['is_hidden' => true]);
            IdolRatingService::adjust($idol, 'review_dispute_approved');
            $idol->notify(new ReviewDisputeApprovedNotification($request->admin_note));
        } else {
            $idol->notify(new ReviewDisputeRejectedNotification($request->admin_note));
        }

        broadcast(new NewNotification('private', $idol->id));

        AdminLogService::log(
            $adminId,
            'resolve_review_dispute',
            'review_dispute',
            $reviewDispute->id,
            [
                'decision' => $request->decision,
                'review_id' => $reviewDispute->review_id,
                'note' => $request->admin_note,
            ]
        );

        return back();
    }

    private function format(ReviewDispute $d): array
    {
        return [
            'id' => $d->id,
            'reason' => $d->reason,
            'status' => $d->status,
            'admin_note' => $d->admin_note,
            'created_at' => $d->created_at->format('d.m.Y H:i'),
            'resolved_at' => $d->resolved_at?->format('d.m.Y H:i'),
            'idol' => [
                'id' => $d->idol->id,
                'name' => $d->idol->name,
                'avatar' => $d->idol->avatar_url,
            ],
            'review' => [
                'id' => $d->review->id,
                'rating' => $d->review->rating,
                'text' => $d->review->text,
                'reviewer' => [
                    'id' => $d->review->reviewer->id,
                    'name' => $d->review->reviewer->name,
                    'avatar' => $d->review->reviewer->avatar_url,
                ],
            ],
        ];
    }
}
