<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminBroadcast;
use App\Models\IdolApplication;
use App\Models\IdolQuizSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'pending_applications_count' => IdolApplication::where('status', 'pending')->count(),
                'total_users'                => User::count(),
                'total_idols'               => User::where('is_idol', true)->count(),
                'users_with_cooldown'        => User::whereNotNull('idol_quiz_cooldown_until')
                    ->where('idol_quiz_cooldown_until', '>', now())
                    ->count(),
            ],
            'recent_applications' => IdolApplication::with('user')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn($app) => [
                    'id'         => $app->id,
                    'status'     => $app->status,
                    'user'       => ['name' => $app->user->name],
                    'created_at' => $app->created_at->toIso8601String(),
                ]),
            'recent_broadcasts' => AdminBroadcast::with('admin')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn($b) => [
                    'id'         => $b->id,
                    'title'      => $b->getTranslations('title'),
                    'title_text' => $b->title,
                    'target'     => $b->target,
                    'created_at' => $b->created_at->toIso8601String(),
                    'admin'      => ['name' => $b->admin->name],
                ]),
            'registrations_by_day' => DB::select("
                SELECT DATE(created_at AT TIME ZONE 'UTC') as date, COUNT(*)::int as count
                FROM users
                WHERE created_at >= NOW() - INTERVAL '14 days'
                GROUP BY 1 ORDER BY 1 ASC
            "),
            'top_idols' => User::where('is_idol', true)
                ->orderByDesc('rating')->limit(5)
                ->get(['id', 'name', 'rating', 'avatar_path'])
                ->map(fn($u) => ['name' => $u->name, 'rating' => $u->rating, 'avatar_url' => $u->avatar_url]),
            'quiz_stats' => (function () {
                $total  = IdolQuizSession::count();
                $passed = IdolQuizSession::where('status', 'passed')->count();
                return [
                    'total'     => $total,
                    'passed'    => $passed,
                    'failed'    => $total - $passed,
                    'pass_rate' => $total > 0 ? round($passed / $total * 100) : 0,
                ];
            })(),
        ]);
    }
}
