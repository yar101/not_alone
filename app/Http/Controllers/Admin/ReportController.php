<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = UserReport::with(['reporter', 'reported', 'reviewer'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->paginate(30)->through(fn(UserReport $r) => [
            'id'          => $r->id,
            'reporter'    => $r->reporter ? ['id' => $r->reporter->id, 'name' => $r->reporter->name, 'email' => $r->reporter->email] : null,
            'reported'    => $r->reported ? ['id' => $r->reported->id, 'name' => $r->reported->name, 'email' => $r->reported->email] : null,
            'reason'      => $r->reason,
            'details'     => $r->details,
            'status'      => $r->status,
            'admin_note'  => $r->admin_note,
            'reviewer'    => $r->reviewer ? ['name' => $r->reviewer->name] : null,
            'reviewed_at' => $r->reviewed_at,
            'created_at'  => $r->created_at,
        ]);

        return Inertia::render('Admin/Reports/Index', [
            'reports'       => $reports,
            'filter_status' => $request->status,
        ]);
    }

    public function show(UserReport $report): Response
    {
        $report->load(['reporter', 'reported', 'reviewer']);

        return Inertia::render('Admin/Reports/Show', [
            'report' => [
                'id'          => $report->id,
                'reason'      => $report->reason,
                'details'     => $report->details,
                'status'      => $report->status,
                'admin_note'  => $report->admin_note,
                'created_at'  => $report->created_at,
                'reviewed_at' => $report->reviewed_at,
                'reporter'    => $report->reporter ? [
                    'id'    => $report->reporter->id,
                    'name'  => $report->reporter->name,
                    'email' => $report->reporter->email,
                ] : null,
                'reported'    => $report->reported ? [
                    'id'    => $report->reported->id,
                    'name'  => $report->reported->name,
                    'email' => $report->reported->email,
                ] : null,
                'reviewer'    => $report->reviewer ? ['name' => $report->reviewer->name] : null,
            ],
        ]);
    }

    public function review(Request $request, UserReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $report->update([
            'status'      => 'reviewed',
            'admin_note'  => $validated['admin_note'] ?? null,
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Жалоба рассмотрена.');
    }

    public function dismiss(Request $request, UserReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $report->update([
            'status'      => 'dismissed',
            'admin_note'  => $validated['admin_note'] ?? null,
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Жалоба отклонена.');
    }
}
