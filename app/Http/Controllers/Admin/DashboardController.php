<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminBroadcast;
use App\Models\AdminLog;
use App\Models\ContentPack;
use App\Models\ContentPackChangeRequest;
use App\Models\IdolApplication;
use App\Models\IdolQuizSession;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\ServiceChangeRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $walletStats = WalletTransaction::where('status', 'completed')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'platform_fee' THEN ABS(amount) ELSE 0 END), 0) as platform_fee_total,
                COALESCE(SUM(CASE WHEN type IN ('order_payout', 'pack_sale') THEN amount ELSE 0 END), 0) as turnover_total,
                COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as deposits_total
            ")
            ->first();

        $platformFeeTotal = (float) ($walletStats->platform_fee_total ?? 0);
        $turnoverTotal = (float) ($walletStats->turnover_total ?? 0);
        $depositsTotal = (float) ($walletStats->deposits_total ?? 0);

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'pending_applications_count' => IdolApplication::where('status', 'pending')->count(),
                'total_users' => User::count(),
                'total_idols' => User::where('is_idol', true)->count(),
                'users_with_cooldown' => User::whereNotNull('idol_quiz_cooldown_until')
                    ->where('idol_quiz_cooldown_until', '>', now())
                    ->count(),
                'active_orders_count' => Order::whereIn('status', [OrderStatus::Paid->value, OrderStatus::Accepted->value])->count(),
                'open_disputes_count' => OrderDispute::where('status', 'open')->count(),
                'pending_content_packs_count' => ContentPack::where('status', 'pending_review')->count(),
                'pending_changes_count' => ContentPackChangeRequest::where('status', 'pending')->count() + ServiceChangeRequest::where('status', 'pending')->count(),
                'platform_fee_total' => $platformFeeTotal,
                'turnover_total' => $turnoverTotal,
                'deposits_total' => $depositsTotal,
            ],
            'recent_applications' => IdolApplication::with('user')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($app) => [
                    'id' => $app->id,
                    'status' => $app->status,
                    'user' => ['name' => $app->user?->name ?? 'Удалённый пользователь'],
                    'created_at' => $app->created_at->toIso8601String(),
                ]),
            'recent_broadcasts' => AdminBroadcast::with('admin')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->getTranslations('title'),
                    'title_text' => $b->title,
                    'target' => $b->target,
                    'created_at' => $b->created_at->toIso8601String(),
                    'admin' => ['name' => $b->admin?->name ?? 'Система'],
                ]),
            'recent_admin_logs' => AdminLog::with('admin')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'admin' => $log->admin ? ['name' => $log->admin->name] : null,
                    'action' => $log->action,
                    'target_type' => $log->target_type,
                    'target_id' => $log->target_id,
                    'details' => $log->details,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            'registrations_by_day' => User::query()
                ->where('created_at', '>=', now()->subDays(14)->startOfDay())
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('DATE(created_at) ASC')
                ->get()
                ->map(fn ($row) => ['date' => (string) $row->date, 'count' => (int) $row->count]),
            'top_idols' => User::where('is_idol', true)
                ->orderByDesc('rating')->limit(5)
                ->get(['id', 'name', 'rating', 'avatar_path'])
                ->map(fn ($u) => ['name' => $u->name, 'rating' => $u->rating, 'avatar_url' => $u->avatar_url]),
            'quiz_stats' => (function () {
                $stats = IdolQuizSession::selectRaw("
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'passed') as passed
                ")->first();

                $total = (int) ($stats->total ?? 0);
                $passed = (int) ($stats->passed ?? 0);

                return [
                    'total' => $total,
                    'passed' => $passed,
                    'failed' => $total - $passed,
                    'pass_rate' => $total > 0 ? round($passed / $total * 100) : 0,
                ];
            })(),
        ]);
    }
}
