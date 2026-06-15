<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Events\NewNotification;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Jobs\NotifyFollowersJob;
use App\Notifications\ServiceApprovedNotification;
use App\Notifications\ServiceRejectedNotification;
use App\Notifications\ServiceRemarksNotification;
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

        $query = Service::with(['user', 'category', 'timeUnit', 'latestReview'])
            ->when($status === 'pending', fn($q) => $q->where('status', 'pending')->whereNull('resubmitted_at'))
            ->when($status === 'resubmitted', fn($q) => $q->where('status', 'pending')->whereNotNull('resubmitted_at'))
            ->when(!in_array($status, ['all', 'pending', 'resubmitted']), fn($q) => $q->where('status', $status))
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
            'resubmitted_at'   => $s->resubmitted_at,
            'category'         => $s->category?->getTranslation('name', 'ru'),
            'time_unit'        => $s->timeUnit?->getTranslation('name', 'ru'),
            'user'             => $s->user ? [
                'id'         => $s->user->id,
                'name'       => $s->user->name,
                'email'      => $s->user->email,
                'avatar_url' => $s->user->avatar_url,
            ] : null,
            'latest_review' => $s->latestReview ? [
                'decision'       => $s->latestReview->decision,
                'flagged_fields' => $s->latestReview->flagged_fields ?? [],
                'field_comments' => $s->latestReview->field_comments ?? [],
            ] : null,
        ]);

        $counts = [
            'pending'     => Service::where('status', 'pending')->whereNull('resubmitted_at')->count(),
            'approved'    => Service::where('status', 'approved')->count(),
            'rejected'    => Service::where('status', 'rejected')->count(),
            'has_remarks' => Service::where('status', 'has_remarks')->count(),
            'resubmitted' => Service::where('status', 'pending')->whereNotNull('resubmitted_at')->count(),
        ];

        $categories = ServiceCategory::orderBy('sort_order')->get()->map(fn($c) => [
            'id'      => $c->id,
            'name_ru' => $c->getTranslation('name', 'ru'),
        ]);

        return Inertia::render('Admin/Services/Index', [
            'moderation_services'    => $services,
            'moderation_filters'     => ['status' => $status, 'search' => $search, 'category_id' => $categoryId],
            'moderation_counts'      => $counts,
            'moderation_categories'  => $categories,
            'active_tab'             => 'moderation',
        ]);
    }

    public function show(Service $service): Response
    {
        $service->load(['user', 'category', 'timeUnit', 'reviews.admin', 'changeRequests.reviewedBy']);

        return Inertia::render('Admin/Services/Show', [
            'service' => [
                'id'          => $service->id,
                'name_ru'     => $service->getTranslation('name', 'ru'),
                'name_en'     => $service->getTranslation('name', 'en', false) ?: null,
                'price'       => $service->price,
                'status'      => $service->status,
                'created_at'  => $service->created_at,
                'category'    => $service->category?->getTranslation('name', 'ru'),
                'time_unit'   => $service->timeUnit?->getTranslation('name', 'ru'),
                'user'        => [
                    'id'         => $service->user->id,
                    'name'       => $service->user->name,
                    'email'      => $service->user->email,
                    'avatar_url' => $service->user->avatar_url,
                ],
                'history' => $service->reviews->map(fn($r) => [
                    'id'             => 'review_' . $r->id,
                    'type'           => 'initial',
                    'decision'       => $r->decision,
                    'flagged_fields' => $r->flagged_fields ?? [],
                    'field_comments' => $r->field_comments ?? [],
                    'created_at'     => $r->created_at->toIso8601String(),
                    'admin'          => $r->admin ? ['name' => $r->admin->name] : null,
                ])->concat(
                    $service->changeRequests
                        ->whereNotNull('reviewed_at')
                        ->map(fn($cr) => [
                            'id'             => 'cr_' . $cr->id,
                            'type'           => 'change_request',
                            'decision'       => $cr->status,
                            'flagged_fields' => $cr->flagged_fields ?? [],
                            'field_comments' => $cr->field_comments ?? [],
                            'admin_comment'  => $cr->admin_comment,
                            'created_at'     => $cr->reviewed_at->toIso8601String(),
                            'admin'          => $cr->reviewedBy ? ['name' => $cr->reviewedBy->name] : null,
                        ])
                )->sortByDesc('created_at')->values(),
            ],
        ]);
    }

    public function decide(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'decision'       => ['required', 'in:approved,has_remarks,rejected'],
            'flagged_fields' => ['nullable', 'array'],
            'flagged_fields.*' => ['string', 'in:name_ru,name_en,price,category_id,time_unit_id'],
            'field_comments' => ['nullable', 'array'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = auth('admin')->user();

        $service->reviews()->create([
            'admin_id'       => $admin->id,
            'decision'       => $data['decision'],
            'flagged_fields' => $data['flagged_fields'] ?? null,
            'field_comments' => $data['field_comments'] ?? null,
        ]);

        if ($data['decision'] === 'approved') {
            $service->update([
                'status'       => 'approved',
                'moderated_by' => $admin->id,
                'moderated_at' => now(),
                'resubmitted_at' => null,
            ]);

            if ($service->user) {
                $service->user->notify(new ServiceApprovedNotification($service));
                broadcast(new NewNotification('private', $service->user->id));
                NotifyFollowersJob::dispatch($service->user, $service);
            }
        } elseif ($data['decision'] === 'rejected') {
            $service->update([
                'status'           => 'rejected',
                'rejection_reason' => $data['rejection_reason'] ?? 'Нарушение правил платформы',
                'moderated_by'     => $admin->id,
                'moderated_at'     => now(),
                'resubmitted_at'   => null,
            ]);

            $service->user?->notify(new ServiceRejectedNotification($service));
            if ($service->user) broadcast(new NewNotification('private', $service->user->id));
        } else { // has_remarks
            $service->update(['status' => 'has_remarks', 'resubmitted_at' => null]);
            $service->user?->notify(new ServiceRemarksNotification($service));
            if ($service->user) broadcast(new NewNotification('private', $service->user->id));
        }

        AdminLogService::log(
            $admin->id,
            'decide_service',
            'service',
            $service->id,
            ['decision' => $data['decision']]
        );

        return back()->with('success', 'Решение отправлено.');
    }

    public function approve(Service $service): RedirectResponse
    {
        // For backwards compatibility or direct action from list
        return $this->decide(new Request(['decision' => 'approved']), $service);
    }

    public function reject(Request $request, Service $service): RedirectResponse
    {
        // For backwards compatibility or direct action from list
        return $this->decide($request->merge(['decision' => 'rejected']), $service);
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

        $services = Service::whereIn('id', $ids)->get();

        foreach ($services as $service) {
            $service->update([
                'status'       => 'approved',
                'moderated_by' => $adminId,
                'moderated_at' => now(),
            ]);
            
            if ($service->user) {
                $service->user->notify(new ServiceApprovedNotification($service));
                broadcast(new NewNotification('private', $service->user->id));
                NotifyFollowersJob::dispatch($service->user, $service);
            }
        }

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

        $services = Service::whereIn('id', $ids)->get();

        foreach ($services as $service) {
            $service->update([
                'status'           => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'moderated_by'     => $adminId,
                'moderated_at'     => now(),
            ]);
            $service->user?->notify(new ServiceRejectedNotification($service));
            if ($service->user) broadcast(new NewNotification('private', $service->user->id));
        }

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
            ->when($status === 'pending', fn($q) => $q->where('status', 'pending')->whereNull('resubmitted_at'))
            ->when($status === 'resubmitted', fn($q) => $q->where('status', 'pending')->whereNotNull('resubmitted_at'))
            ->when(!in_array($status, ['all', 'pending', 'resubmitted']), fn($q) => $q->where('status', $status))
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($u) =>
                       $u->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%"))
            ))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId));
    }
}
