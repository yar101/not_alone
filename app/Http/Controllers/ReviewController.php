<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\NewNotification;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewEpithet;
use App\Models\User;
use App\Notifications\NewReviewNotification;
use App\Services\IdolRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function epithets(): JsonResponse
    {
        return response()->json(ReviewEpithet::all(['id', 'label']));
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($order->customer_id === $user->id, 403);
        abort_unless($order->status === OrderStatus::Completed, 422, 'Отзыв можно оставить только на выполненный заказ');
        abort_if(
            Review::where('reviewer_id', $user->id)->where('idol_id', $order->idol_id)->exists(),
            422,
            'already_reviewed'
        );

        $request->validate([
            'rating'   => 'required|integer|min:1|max:5',
            'text'     => 'nullable|string|max:250',
            'epithets' => 'nullable|array',
            'epithets.*' => 'integer|exists:review_epithets,id',
        ]);

        $order->loadMissing(['items.service.category', 'items.service.timeUnit']);

        $snapshot = $order->items->map(fn($i) => [
            'name'          => $i->service?->name,
            'category_name' => $i->service?->category?->name,
            'price'         => $i->service?->price,
            'time_unit'     => $i->service?->timeUnit?->name,
            'quantity'      => $i->quantity ?? 1,
        ])->values()->all();

        $review = Review::create([
            'reviewer_id'       => $user->id,
            'idol_id'           => $order->idol_id,
            'order_id'          => $order->id,
            'rating'            => $request->rating,
            'text'              => $request->text,
            'services_snapshot' => $snapshot,
        ]);

        $review->epithets()->sync($request->epithets ?? []);

        IdolRatingService::adjust($order->idol, 'review_' . $review->rating . 'star');

        $order->idol->notify(new NewReviewNotification($review));
        broadcast(new NewNotification('private', $order->idol_id));

        return response()->json(['success' => true]);
    }

    public function index(Request $request, User $user): JsonResponse
    {
        abort_unless($user->is_idol, 404);

        $isOwner = $request->user()?->id === $user->id;

        $query = Review::where('idol_id', $user->id)
                       ->where('is_hidden', false)
                       ->with(['reviewer', 'epithets']);

        if ($isOwner) {
            $query->with(['disputes' => fn($q) => $q->latest('created_at')->limit(1)]);
        }

        match ($request->get('sort')) {
            'oldest'      => $query->oldest(),
            'rating_desc' => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'rating_asc'  => $query->orderBy('rating')->orderByDesc('created_at'),
            default       => $query->latest(),
        };

        $reviews = $query->paginate(10);

        $reviewIds = Review::where('idol_id', $user->id)->where('is_hidden', false)->pluck('id');
        $epithetCounts = \DB::table('review_epithet_review')
            ->join('review_epithets', 'review_epithets.id', '=', 'review_epithet_review.review_epithet_id')
            ->whereIn('review_epithet_review.review_id', $reviewIds)
            ->selectRaw('review_epithets.id, review_epithets.label, COUNT(*) as count')
            ->groupBy('review_epithets.id', 'review_epithets.label')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'reviews'    => $reviews->map(fn(Review $r) => [
                'id'               => $r->id,
                'rating'           => $r->rating,
                'text'             => $r->text,
                'services_snapshot'=> $r->services_snapshot,
                'epithets'         => $r->epithets->map(fn($e) => ['id' => $e->id, 'label' => $e->label])->values(),
                'reviewer'         => [
                    'id'         => $r->reviewer->id,
                    'name'       => $r->reviewer->name,
                    'avatar_url' => $r->reviewer->avatar_url,
                ],
                'created_at'       => $r->created_at->toISOString(),
                'dispute_status'   => $isOwner ? ($r->disputes->first()?->status ?? null) : null,
            ])->values(),
            'total'      => $reviews->total(),
            'has_more'   => $reviews->hasMorePages(),
            'epithet_counts' => $epithetCounts->map(fn($e) => [
                'id'    => $e->id,
                'label' => $e->label,
                'count' => (int) $e->count,
            ])->values(),
        ]);
    }
}
