<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewEpithet;
use App\Models\User;
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

        return response()->json(['success' => true]);
    }

    public function index(Request $request, User $user): JsonResponse
    {
        abort_unless($user->is_idol, 404);

        $reviews = Review::where('idol_id', $user->id)
            ->with(['reviewer', 'epithets'])
            ->latest()
            ->paginate(20);

        $avg = Review::where('idol_id', $user->id)->avg('rating');

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
            ])->values(),
            'total'      => $reviews->total(),
            'avg_rating' => $avg ? round($avg, 1) : null,
        ]);
    }
}
