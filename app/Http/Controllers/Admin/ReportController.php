<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use App\Services\AdminLogService;
use App\Services\IdolRatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $reports = $query->paginate(30)->withQueryString()->through(fn (UserReport $r) => [
            'id' => $r->id,
            'reporter' => $r->reporter ? ['id' => $r->reporter->id, 'name' => $r->reporter->name, 'email' => $r->reporter->email] : null,
            'reported' => $r->reported ? ['id' => $r->reported->id, 'name' => $r->reported->name, 'email' => $r->reported->email] : null,
            'reason' => $r->reason,
            'details' => $r->details,
            'status' => $r->status,
            'admin_note' => $r->admin_note,
            'reviewer' => $r->reviewer ? ['name' => $r->reviewer->name] : null,
            'reviewed_at' => $r->reviewed_at,
            'created_at' => $r->created_at,
        ]);

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'filter_status' => $request->status,
        ]);
    }

    public function show(UserReport $report): Response
    {
        $report->load(['reporter', 'reported', 'reviewer']);

        return Inertia::render('Admin/Reports/Show', [
            'report' => [
                'id' => $report->id,
                'reason' => $report->reason,
                'details' => $report->details,
                'status' => $report->status,
                'admin_note' => $report->admin_note,
                'created_at' => $report->created_at,
                'reviewed_at' => $report->reviewed_at,
                'reporter' => $report->reporter ? [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->name,
                    'email' => $report->reporter->email,
                ] : null,
                'reported' => $report->reported ? [
                    'id' => $report->reported->id,
                    'name' => $report->reported->name,
                    'email' => $report->reported->email,
                ] : null,
                'reviewer' => $report->reviewer ? ['name' => $report->reviewer->name] : null,
            ],
        ]);
    }

    public function review(Request $request, UserReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $adminId = auth('admin')->id();
        $alreadyHandled = false;

        DB::transaction(function () use ($report, $validated, $adminId, &$alreadyHandled) {
            $lockedReport = UserReport::where('id', $report->id)->lockForUpdate()->first();
            if (! $lockedReport || $lockedReport->status !== 'pending') {
                $alreadyHandled = true;

                return;
            }

            $lockedReport->update([
                'status' => 'reviewed',
                'admin_note' => $validated['admin_note'] ?? null,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            $reported = $lockedReport->reported;
            if ($reported?->is_idol) {
                IdolRatingService::adjust($reported, 'report_accepted');
            }

            AdminLogService::log(
                $adminId,
                'review_report',
                'user_report',
                $lockedReport->id,
                ['admin_note' => $validated['admin_note'] ?? null, 'reported_id' => $lockedReport->reported_id]
            );
        });

        if ($alreadyHandled) {
            return back()->withErrors(['report' => 'Жалоба уже рассмотрена.']);
        }

        return back()->with('success', 'Жалоба рассмотрена.');
    }

    public function dismiss(Request $request, UserReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $adminId = auth('admin')->id();
        $alreadyHandled = false;

        DB::transaction(function () use ($report, $validated, $adminId, &$alreadyHandled) {
            $lockedReport = UserReport::where('id', $report->id)->lockForUpdate()->first();
            if (! $lockedReport || $lockedReport->status !== 'pending') {
                $alreadyHandled = true;

                return;
            }

            $lockedReport->update([
                'status' => 'dismissed',
                'admin_note' => $validated['admin_note'] ?? null,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            AdminLogService::log(
                $adminId,
                'dismiss_report',
                'user_report',
                $lockedReport->id,
                ['admin_note' => $validated['admin_note'] ?? null, 'reported_id' => $lockedReport->reported_id]
            );
        });

        if ($alreadyHandled) {
            return back()->withErrors(['report' => 'Жалоба уже обработана.']);
        }

        return back()->with('success', 'Жалоба отклонена.');
    }
}
