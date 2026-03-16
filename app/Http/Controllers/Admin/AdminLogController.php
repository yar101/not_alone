<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLog;
use Illuminate\Http\Request;
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

        $logs = $query->paginate(50)->through(fn(AdminLog $log) => [
            'id'          => $log->id,
            'admin'       => $log->admin ? ['name' => $log->admin->name] : null,
            'action'      => $log->action,
            'target_type' => $log->target_type,
            'target_id'   => $log->target_id,
            'details'     => $log->details,
            'created_at'  => $log->created_at,
        ]);

        return Inertia::render('Admin/Logs/Index', [
            'logs'   => $logs,
            'filter' => [
                'action'   => $request->action,
                'admin_id' => $request->admin_id,
            ],
            'admins' => Admin::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
