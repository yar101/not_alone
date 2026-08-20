<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowController extends Controller
{
    /**
     * Display a listing of followed idols.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $idols = $user->following()
            ->select(['users.id', 'users.name', 'users.avatar_path', 'users.active_frame_id', 'users.gender', 'users.birth_date', 'users.rating', 'users.is_idol'])
            ->with('activeFrame')
            ->paginate(20);

        return Inertia::render('Tracked/Index', [
            'idols' => $idols,
        ]);
    }

    /**
     * Toggle follow status for an idol.
     */
    public function toggle(User $user): RedirectResponse
    {
        $follower = auth()->user();

        // Cannot follow yourself
        if ($follower->id === $user->id) {
            return back();
        }

        // Only idols can be followed (as per requirement)
        if (! $user->is_idol) {
            return back();
        }

        if ($follower->isFollowing($user->id)) {
            $follower->unfollow($user->id);
            $message = 'Вы больше не отслеживаете этого пользователя.';
        } else {
            $follower->follow($user->id);
            $message = 'Теперь вы отслеживаете этого пользователя.';
        }

        return back()->with('success', $message);
    }
}
