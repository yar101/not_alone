<?php

namespace App\Http\Controllers\Admin;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\ServiceChangeRequest;
use App\Notifications\ServiceChangeApprovedNotification;
use App\Notifications\ServiceChangeRejectedNotification;
use App\Notifications\ServiceChangeRemarksNotification;
use App\Services\AdminLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceChangeRequestController extends Controller
{
    public function index(): Response
    {
        $requests = ServiceChangeRequest::with(['service.user'])
            ->whereIn('status', ['pending', 'has_remarks'])
            ->latest()
            ->paginate(50)
            ->through(fn (ServiceChangeRequest $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'changed_fields' => $r->changed_fields,
                'created_at' => $r->created_at,
                'service' => $r->service ? [
                    'id' => $r->service->id,
                    'name' => $r->service->getTranslation('name', 'ru'),
                    'user' => $r->service->user ? [
                        'id' => $r->service->user->id,
                        'name' => $r->service->user->name,
                        'email' => $r->service->user->email,
                        'avatar_url' => $r->service->user->avatar_url,
                    ] : null,
                ] : null,
            ]);

        return Inertia::render('Admin/Services/ChangeRequests', [
            'requests' => $requests,
        ]);
    }

    public function show(ServiceChangeRequest $changeRequest): Response
    {
        $changeRequest->load(['service.user', 'service.category', 'service.timeUnit', 'service.reviews.admin', 'service.changeRequests.reviewedBy', 'pendingCategory', 'pendingTimeUnit']);

        $service = $changeRequest->service;

        $fields = [];
        foreach ($changeRequest->changed_fields as $field) {
            $current = match ($field) {
                'name' => [
                    'ru' => $service->getTranslation('name', 'ru'),
                    'en' => $service->getTranslation('name', 'en', false) ?: null,
                ],
                'category_id' => [
                    'id' => $service->category_id,
                    'name' => $service->category?->getTranslation('name', 'ru'),
                ],
                'time_unit_id' => [
                    'id' => $service->time_unit_id,
                    'name' => $service->timeUnit?->getTranslation('name', 'ru'),
                ],
                default => $service->{$field},
            };

            $pending = match ($field) {
                'name' => $changeRequest->pending_name,
                'category_id' => [
                    'id' => $changeRequest->pending_category_id,
                    'name' => $changeRequest->pendingCategory?->getTranslation('name', 'ru'),
                ],
                'time_unit_id' => [
                    'id' => $changeRequest->pending_time_unit_id,
                    'name' => $changeRequest->pendingTimeUnit?->getTranslation('name', 'ru'),
                ],
                default => $changeRequest->{"pending_{$field}"},
            };

            $fields[$field] = [
                'current' => $current,
                'pending' => $pending,
                'flagged' => in_array($field, $changeRequest->flagged_fields ?? []),
                'admin_comment' => ($changeRequest->field_comments ?? [])[$field] ?? '',
            ];
        }

        return Inertia::render('Admin/Services/ChangeRequestShow', [
            'changeRequest' => $changeRequest,
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'status' => $service->status,
                'user' => [
                    'id' => $service->user->id,
                    'name' => $service->user->name,
                    'avatar_url' => $service->user->avatar_url,
                ],
                'history' => $service->reviews->map(fn ($r) => [
                    'id' => 'review_'.$r->id,
                    'type' => 'initial',
                    'decision' => $r->decision,
                    'flagged_fields' => $r->flagged_fields ?? [],
                    'field_comments' => $r->field_comments ?? [],
                    'created_at' => $r->created_at->toIso8601String(),
                    'admin' => $r->admin ? ['name' => $r->admin->name] : null,
                ])->concat(
                    $service->changeRequests
                        ->where('id', '!=', $changeRequest->id)
                        ->whereNotNull('reviewed_at')
                        ->map(fn ($cr) => [
                            'id' => 'cr_'.$cr->id,
                            'type' => 'change_request',
                            'decision' => $cr->status,
                            'flagged_fields' => $cr->flagged_fields ?? [],
                            'field_comments' => $cr->field_comments ?? [],
                            'admin_comment' => $cr->admin_comment,
                            'created_at' => $cr->reviewed_at->toIso8601String(),
                            'admin' => $cr->reviewedBy ? ['name' => $cr->reviewedBy->name] : null,
                        ])
                )->sortByDesc('created_at')->values(),
            ],
            'fields' => $fields,
        ]);
    }

    public function decide(Request $request, ServiceChangeRequest $changeRequest): RedirectResponse
    {
        abort_if(! in_array($changeRequest->status, ['pending', 'has_remarks']), 422);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,has_remarks,rejected'],
            'flagged_fields' => ['sometimes', 'array'],
            'flagged_fields.*' => ['string', 'in:name,price,category_id,time_unit_id'],
            'field_comments' => ['sometimes', 'array'],
            'field_comments.*' => ['nullable', 'string', 'max:500'],
            'admin_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $admin = auth('admin')->user();
        $service = $changeRequest->service;

        if ($data['decision'] === 'approved') {
            DB::transaction(function () use ($changeRequest, $service, $admin, $data) {
                $updates = [];
                foreach ($changeRequest->changed_fields as $field) {
                    if ($field === 'name') {
                        $updates['name'] = $changeRequest->pending_name;
                    } else {
                        $updates[$field] = $changeRequest->{"pending_{$field}"};
                    }
                }
                $service->update($updates);

                $changeRequest->update([
                    'status' => 'approved',
                    'flagged_fields' => $data['flagged_fields'] ?? null,
                    'field_comments' => $data['field_comments'] ?? null,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ]);

                $service->user->notify(new ServiceChangeApprovedNotification($service, $changeRequest->changed_fields, $data['flagged_fields'] ?? null, $data['field_comments'] ?? null));
                broadcast(new NewNotification('private', $service->user->id));
            });

            AdminLogService::log($admin->id, 'approve_service_change', 'service_change_request', $changeRequest->id, [
                'fields' => $changeRequest->changed_fields,
            ]);

        } elseif ($data['decision'] === 'has_remarks') {
            $flaggedFields = $data['flagged_fields'] ?? [];
            // Only flag fields that are actually changed
            $flaggedFields = array_values(array_intersect($flaggedFields, $changeRequest->changed_fields));
            $fieldComments = array_intersect_key($data['field_comments'] ?? [], array_flip($flaggedFields));

            $changeRequest->update([
                'status' => 'has_remarks',
                'flagged_fields' => $flaggedFields,
                'field_comments' => $fieldComments ?: null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $service->user->notify(new ServiceChangeRemarksNotification($service));
            broadcast(new NewNotification('private', $service->user->id));

            AdminLogService::log($admin->id, 'remarks_service_change', 'service_change_request', $changeRequest->id, [
                'flagged_fields' => $flaggedFields,
            ]);

        } else { // rejected
            $changeRequest->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_comment' => $data['admin_comment'] ?? null,
            ]);

            $service->user->notify(new ServiceChangeRejectedNotification($service, $data['admin_comment'] ?? null));
            broadcast(new NewNotification('private', $service->user->id));

            AdminLogService::log($admin->id, 'reject_service_change', 'service_change_request', $changeRequest->id, [
                'fields' => $changeRequest->changed_fields,
            ]);
        }

        return back()->with('success', 'Решение отправлено.');
    }
}
