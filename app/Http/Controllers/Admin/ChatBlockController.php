<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatBlock;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ChatBlockController extends Controller
{
    public function index(): Response
    {
        $blocks = ChatBlock::active()
            ->with(['blocker', 'blocked'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $topRows = ChatBlock::active()
            ->selectRaw('blocked_id, COUNT(*) as block_count')
            ->groupBy('blocked_id')
            ->orderByDesc('block_count')
            ->limit(10)
            ->get();

        $topUserIds = $topRows->pluck('blocked_id')->filter()->unique();
        $usersMap = $topUserIds->isNotEmpty()
            ? User::withTrashed()->whereIn('id', $topUserIds)->get()->keyBy('id')
            : collect();

        $topBlocked = $topRows
            ->map(function ($row) use ($usersMap) {
                $user = $usersMap->get($row->blocked_id);

                return [
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar_url' => $user->avatar_url,
                        'is_deleted' => (bool) $user->trashed(),
                    ] : null,
                    'block_count' => $row->block_count,
                ];
            })
            ->filter(fn ($r) => $r['user'] !== null)
            ->values();

        return Inertia::render('Admin/ChatBlocks/Index', [
            'blocks' => $blocks,
            'top_blocked' => $topBlocked,
        ]);
    }
}
