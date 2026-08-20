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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function epithets(): JsonResponse
    {
        $epithets = Cache::rememberForever('review_epithets_list', fn () => ReviewEpithet::all(['id', 'label']));

        return response()->json($epithets);
    }

    public function store(\App\Http\Requests\Review\StoreReviewRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($order->customer_id === $user->id, 403);
        abort_unless($order->status === OrderStatus::Completed, 422, 'Отзыв можно оставить только на выполненный заказ');
        abort_if(
            Review::where('reviewer_id', $user->id)->where('idol_id', $order->idol_id)->exists(),
            422,
            'already_reviewed'
        );

        $order->loadMissing('idol');
        abort_if($order->idol->isActiveBanned(), 422, 'user_banned');

        $order->loadMissing(['items.service.category', 'items.service.timeUnit']);

        $snapshot = $order->items->map(fn ($i) => [
            'name'          => $i->service?->name,
            'category_name' => $i->service?->category?->name,
            'price'         => $i->service?->price,
            'time_unit'     => $i->service?->timeUnit?->name,
            'quantity'      => $i->quantity ?? 1,
        ])->values()->all();

        DB::transaction(function () use ($user, $order, $request, $snapshot) {
            $review = Review::create([
                'reviewer_id'       => $user->id,
                'idol_id'           => $order->idol_id,
                'order_id'          => $order->id,
                'rating'            => $request->rating,
                'text'              => $request->text,
                'services_snapshot' => $snapshot,
            ]);

            $review->epithets()->sync($request->epithets ?? []);

            IdolRatingService::adjust($order->idol, 'review_'.$review->rating.'star');

            DB::afterCommit(function () use ($order, $review) {
                $order->idol->notify(new NewReviewNotification($review));
                try {
                    broadcast(new NewNotification('private', $order->idol_id));
                } catch (\Throwable $e) {
                    Log::warning('Broadcast failed in ReviewController::store: '.$e->getMessage());
                }
            });
        });

        return response()->json(['success' => true]);
    }

    public function index(Request $request, User $user): JsonResponse
    {
        abort_unless($user->is_idol, 404);

        $isOwner = $request->user()?->id === $user->id;

        $query = Review::where('idol_id', $user->id)
                       ->where('is_hidden', false)
                       ->with(['reviewer.activeFrame', 'epithets']);

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

        $epithetCounts = \DB::table('review_epithet_review')
            ->join('review_epithets', 'review_epithets.id', '=', 'review_epithet_review.review_epithet_id')
            ->join('reviews', 'reviews.id', '=', 'review_epithet_review.review_id')
            ->where('reviews.idol_id', $user->id)
            ->where('reviews.is_hidden', false)
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
                    'id'                => $r->reviewer->id,
                    'name'              => $r->reviewer->name,
                    'avatar_url'        => $r->reviewer->avatar_url,
                    'active_frame'      => $r->reviewer->activeFrame,
                    'gender'            => $r->reviewer->gender,
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
