<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AdminLog::with('admin')->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->target_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($search = $request->input('search')) {
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $likeOp = $isPgsql ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('target_id', 'like', "%{$search}%")
                    ->orWhereRaw("CAST(details AS TEXT) {$likeOp} ?", ["%{$search}%"]);
            });
        }

        $logs = $query->paginate(50)->withQueryString()->through(fn (AdminLog $log) => [
            'id' => $log->id,
            'admin' => $log->admin ? ['name' => $log->admin->name] : null,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id,
            'details' => $log->details,
            'created_at' => $log->created_at,
        ]);

        $availableActions = AdminLog::distinct()->pluck('action')->filter()->values();
        $availableTargetTypes = AdminLog::distinct()->pluck('target_type')->filter()->values();

        return Inertia::render('Admin/Logs/Index', [
            'logs' => $logs,
            'filter' => [
                'action' => $request->action,
                'admin_id' => $request->admin_id,
                'target_type' => $request->target_type,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
            ],
            'admins' => Admin::orderBy('name')->get(['id', 'name']),
            'available_actions' => $availableActions,
            'available_target_types' => $availableTargetTypes,
        ]);
    }
}
