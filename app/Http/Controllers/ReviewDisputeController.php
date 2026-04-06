<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewDispute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewDisputeController extends Controller
{
    public function store(Request $request, Review $review): JsonResponse
    {
        $user = $request->user();

        abort_unless($review->idol_id === $user->id, 403);
        abort_if($review->is_hidden, 422, 'already_hidden');

        abort_if(
            ReviewDispute::where('review_id', $review->id)
                         ->where('status', 'pending')
                         ->exists(),
            422,
            'dispute_pending'
        );

        $request->validate([
            'reason' => 'required|string|max:250',
        ]);

        ReviewDispute::create([
            'review_id' => $review->id,
            'idol_id'   => $user->id,
            'reason'    => $request->reason,
        ]);

        return response()->json(['success' => true]);
    }
}
