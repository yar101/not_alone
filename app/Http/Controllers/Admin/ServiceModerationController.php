<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\AdminLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $status     = $request->input('status', 'pending');
        $search     = $request->input('search');
        $categoryId = $request->input('category_id');

        $query = Service::with(['user', 'category', 'timeUnit'])
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($u) =>
                       $u->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%"))
            ))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId));

        $services = $query->latest()->paginate(50)->through(fn(Service $s) => [
            'id'               => $s->id,
            'name'             => $s->name,
            'price'            => $s->price,
            'status'           => $s->status,
            'rejection_reason' => $s->rejection_reason,
            'created_at'       => $s->created_at,
            'category'         => $s->category?->name,
            'time_unit'        => $s->timeUnit?->name,
            'user'             => $s->user ? [
                'id'         => $s->user->id,
                'name'       => $s->user->name,
                'email'      => $s->user->email,
                'avatar_url' => $s->user->avatar_url,
            ] : null,
        ]);

        $counts = [
            'pending'  => Service::where('status', 'pending')->count(),
            'approved' => Service::where('status', 'approved')->count(),
            'rejected' => Service::where('status', 'rejected')->count(),
        ];

        $categories = ServiceCategory::orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Services/Index', [
            'moderation_services'    => $services,
            'moderation_filters'     => ['status' => $status, 'search' => $search, 'category_id' => $categoryId],
            'moderation_counts'      => $counts,
            'moderation_categories'  => $categories,
            'active_tab'             => 'moderation',
        ]);
    }

    public function approve(Service $service): RedirectResponse
    {
        $service->update([
            'status'       => 'approved',
            'moderated_by' => auth('admin')->id(),
            'moderated_at' => now(),
        ]);

        AdminLogService::log(auth('admin')->id(), 'approve_service', 'service', $service->id);

        return back()->with('success', 'Услуга одобрена.');
    }

    public function reject(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $service->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'moderated_by'     => auth('admin')->id(),
            'moderated_at'     => now(),
        ]);

        AdminLogService::log(
            auth('admin')->id(),
            'reject_service',
            'service',
            $service->id,
            ['reason' => $validated['rejection_reason']]
        );

        return back()->with('success', 'Услуга отклонена.');
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $adminId = auth('admin')->id();

        if ($request->boolean('filter_all')) {
            $query = $this->buildFilterQuery($request);
            $ids   = $query->pluck('id');
        } else {
            $ids = collect($request->input('ids', []));
        }

        Service::whereIn('id', $ids)->update([
            'status'       => 'approved',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
        ]);

        AdminLogService::log($adminId, 'bulk_approve_services', 'service', null, ['count' => $ids->count()]);

        return back()->with('success', "Одобрено услуг: {$ids->count()}.");
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $adminId = auth('admin')->id();

        if ($request->boolean('filter_all')) {
            $query = $this->buildFilterQuery($request);
            $ids   = $query->pluck('id');
        } else {
            $ids = collect($request->input('ids', []));
        }

        Service::whereIn('id', $ids)->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'moderated_by'     => $adminId,
            'moderated_at'     => now(),
        ]);

        AdminLogService::log($adminId, 'bulk_reject_services', 'service', null, [
            'count'  => $ids->count(),
            'reason' => $validated['rejection_reason'],
        ]);

        return back()->with('success', "Отклонено услуг: {$ids->count()}.");
    }

    private function buildFilterQuery(Request $request)
    {
        $status     = $request->input('status', 'pending');
        $search     = $request->input('search');
        $categoryId = $request->input('category_id');

        return Service::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($u) =>
                       $u->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%"))
            ))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId));
    }
}
