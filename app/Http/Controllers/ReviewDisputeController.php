<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewDispute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewDisputeController extends Controller
{
    public function store(Request $request, Review $review): JsonResponse
    {
        $user = $request->user();

        abort_unless($review->idol_id === $user->id, 403);
        abort_if($review->is_hidden, 422, 'already_hidden');

        $validated = $request->validate([
            'reason' => 'required|string|max:250',
        ]);

        DB::transaction(function () use ($review, $user, $validated) {
            $lockedReview = Review::where('id', $review->id)->lockForUpdate()->firstOrFail();

            abort_if($lockedReview->is_hidden, 422, 'already_hidden');

            abort_if(
                ReviewDispute::where('review_id', $lockedReview->id)
                    ->where('status', 'pending')
                    ->exists(),
                422,
                'dispute_pending'
            );

            abort_if(
                ReviewDispute::where('review_id', $lockedReview->id)->count() >= 2,
                422,
                'max_disputes_reached'
            );

            ReviewDispute::create([
                'review_id' => $lockedReview->id,
                'idol_id' => $user->id,
                'reason' => $validated['reason'],
            ]);
        });

        return response()->json(['success' => true]);
    }
}
