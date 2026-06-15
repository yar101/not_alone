<?php

namespace App\Http\Controllers\Admin;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\ContentPackChangeRequest;
use App\Notifications\ContentPackChangeApprovedNotification;
use App\Notifications\ContentPackChangeRejectedNotification;
use App\Notifications\ContentPackChangeRemarksNotification;
use App\Services\AdminLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContentPackChangeRequestController extends Controller
{
    public function index(): Response
    {
        $requests = ContentPackChangeRequest::with(['contentPack.user'])
            ->whereIn('status', ['pending', 'has_remarks'])
            ->latest()
            ->paginate(50);

        return Inertia::render('Admin/ContentPacks/ChangeRequests', [
            'requests' => $requests,
        ]);
    }

    public function show(ContentPackChangeRequest $changeRequest): Response
    {
        $changeRequest->load(['contentPack.user', 'contentPack.photos']);

        $pack = $changeRequest->contentPack;

        $fields = [];
        foreach ($changeRequest->changed_fields as $field) {
            $fields[$field] = [
                'current'        => $pack->{$field},
                'pending'        => $changeRequest->{"pending_{$field}"},
                'flagged'        => in_array($field, $changeRequest->flagged_fields ?? []),
                'admin_comment'  => ($changeRequest->field_comments ?? [])[$field] ?? '',
            ];
        }

        return Inertia::render('Admin/ContentPacks/ChangeRequestShow', [
            'changeRequest' => $changeRequest,
            'pack'          => [
                'id'         => $pack->id,
                'title'      => $pack->title,
                'cover_url'  => $pack->cover_url,
                'status'     => $pack->status,
                'user'       => [
                    'id'         => $pack->user->id,
                    'name'       => $pack->user->name,
                    'avatar_url' => $pack->user->avatar_url,
                ],
                'photos' => $pack->photos->map(fn ($ph) => [
                    'id'  => $ph->id,
                    'url' => $ph->url,
                ])->values(),
            ],
            'fields' => $fields,
        ]);
    }

    public function decide(Request $request, ContentPackChangeRequest $changeRequest): RedirectResponse
    {
        abort_if(!in_array($changeRequest->status, ['pending', 'has_remarks']), 422);

        $data = $request->validate([
            'decision'                  => ['required', 'in:approved,has_remarks,rejected'],
            'flagged_fields'            => ['sometimes', 'array'],
            'flagged_fields.*'          => ['string', 'in:title,description,price'],
            'field_comments'            => ['sometimes', 'array'],
            'field_comments.*'          => ['nullable', 'string', 'max:500'],
            'admin_comment'             => ['nullable', 'string', 'max:1000'],
        ]);

        $admin = auth('admin')->user();
        $pack  = $changeRequest->contentPack;

        if ($data['decision'] === 'approved') {
            DB::transaction(function () use ($changeRequest, $pack, $admin) {
                $updates = [];
                foreach ($changeRequest->changed_fields as $field) {
                    $updates[$field] = $changeRequest->{"pending_{$field}"};
                }
                $pack->update($updates);

                $changeRequest->update([
                    'status'      => 'approved',
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ]);

                $pack->user->notify(new ContentPackChangeApprovedNotification($pack, $changeRequest->changed_fields));
                broadcast(new NewNotification('private', $pack->user->id));
            });

            AdminLogService::log($admin->id, 'approve_content_pack_change', 'content_pack_change_request', $changeRequest->id, [
                'fields' => $changeRequest->changed_fields,
            ]);

        } elseif ($data['decision'] === 'has_remarks') {
            $flaggedFields = $data['flagged_fields'] ?? [];
            // Only flag fields that are actually changed
            $flaggedFields = array_values(array_intersect($flaggedFields, $changeRequest->changed_fields));
            $fieldComments = array_intersect_key($data['field_comments'] ?? [], array_flip($flaggedFields));

            $changeRequest->update([
                'status'        => 'has_remarks',
                'flagged_fields' => $flaggedFields,
                'field_comments' => $fieldComments ?: null,
                'reviewed_by'   => $admin->id,
                'reviewed_at'   => now(),
            ]);

            $pack->user->notify(new ContentPackChangeRemarksNotification($pack));
            broadcast(new NewNotification('private', $pack->user->id));

            AdminLogService::log($admin->id, 'remarks_content_pack_change', 'content_pack_change_request', $changeRequest->id, [
                'flagged_fields' => $flaggedFields,
            ]);

        } else { // rejected
            $changeRequest->update([
                'status'        => 'rejected',
                'reviewed_by'   => $admin->id,
                'reviewed_at'   => now(),
                'admin_comment' => $data['admin_comment'] ?? null,
            ]);

            $pack->user->notify(new ContentPackChangeRejectedNotification($pack, $data['admin_comment'] ?? null));
            broadcast(new NewNotification('private', $pack->user->id));

            AdminLogService::log($admin->id, 'reject_content_pack_change', 'content_pack_change_request', $changeRequest->id, [
                'fields' => $changeRequest->changed_fields,
            ]);
        }

        return back()->with('success', 'Решение отправлено.');
    }
}
