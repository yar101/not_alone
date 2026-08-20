<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdolRatingLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RatingLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = IdolRatingLog::with('user')->latest('created_at');

        if ($request->filled('idol_id')) {
            $query->where('user_id', $request->idol_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)->through(fn (IdolRatingLog $log) => [
            'id' => $log->id,
            'idol' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            'event' => $log->event,
            'delta' => $log->delta,
            'note' => $log->note,
            'created_at' => $log->created_at,
        ]);

        return Inertia::render('Admin/RatingLogs/Index', [
            'logs' => $logs,
            'idols' => User::where('is_idol', true)->orderBy('name')->get(['id', 'name']),
            'filter' => $request->only(['idol_id', 'date_from', 'date_to']),
        ]);
    }
}
