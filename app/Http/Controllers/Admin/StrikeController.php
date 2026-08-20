<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserStrike;
use App\Notifications\UserStrikeNotification;
use App\Services\AdminLogService;
use App\Services\IdolRatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StrikeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');

        $users = [];
        if ($search) {
            $users = User::withCount(['strikes' => function ($query) {
                $query->where('expires_at', '>', now());
            }])
                ->where(function ($q) use ($search) {
                    if (is_numeric($search)) {
                        $q->where('id', (int) $search);
                    }
                    $q->orWhere('name', 'ilike', '%'.$search.'%')
                        ->orWhere('email', 'ilike', '%'.$search.'%');
                })
                ->select('id', 'name', 'email', 'avatar_path', 'is_idol', 'rating')
                ->take(20)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar_url' => $user->avatar_url,
                        'is_idol' => $user->is_idol,
                        'rating' => $user->rating,
                        'active_strikes_count' => $user->strikes_count,
                    ];
                });
        }

        return Inertia::render('Admin/Strikes/Index', [
            'users' => $users,
            'filters' => ['q' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'rating_deducted' => 'nullable|numeric|in:0,-0.1,-0.3,-0.5,-1.0',
            'admin_note' => 'nullable|string|max:2000',

            // Ban fields if it's a 3rd strike
            'ban_reason' => 'nullable|string|max:1000',
            'banned_until' => 'nullable|date|after:now',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $activeStrikes = $user->activeStrikesCount();

        // 3rd strike triggers ban validation
        if ($activeStrikes >= 2) {
            $request->validate([
                'ban_reason' => 'required|string|max:1000',
            ]);
        }

        DB::transaction(function () use ($user, $validated, $activeStrikes) {
            // Create strike (expires in 6 months)
            $strike = UserStrike::create([
                'user_id' => $user->id,
                'admin_id' => auth('admin')->id(),
                'rating_deducted' => $validated['rating_deducted'] ?? 0,
                'admin_note' => $validated['admin_note'] ?? null,
                'expires_at' => now()->addMonths(6),
            ]);

            // Deduct rating for idol via standard rating service
            if ($user->is_idol && ! empty($validated['rating_deducted']) && (float) $validated['rating_deducted'] < 0) {
                IdolRatingService::adjust(
                    $user,
                    'strike',
                    (float) $validated['rating_deducted'],
                    'Страйк от администрации: '.($validated['admin_note'] ?? 'без причины')
                );
            }

            // Apply ban if needed
            if ($activeStrikes >= 2) {
                $user->update([
                    'is_banned' => true,
                    'banned_at' => now(),
                    'banned_until' => $validated['banned_until'] ?? null,
                    'ban_reason' => $validated['ban_reason'],
                    'banned_by' => auth('admin')->id(),
                ]);

                // Invalidate sessions
                try {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                } catch (\Throwable) {
                    // sessions table might not exist if driver is redis/file/array
                }

                AdminLogService::log(
                    auth('admin')->id(),
                    'ban_user',
                    'user',
                    $user->id,
                    ['reason' => $validated['ban_reason'], 'until' => $validated['banned_until'] ?? null, 'auto_strike_ban' => true]
                );
            }

            // Notify user (Mail + Database)
            $user->notify(new UserStrikeNotification($strike));
        });

        return back()->with('success', 'Страйк успешно выдан'.($activeStrikes >= 2 ? ' и пользователь забанен' : ''));
    }
}
