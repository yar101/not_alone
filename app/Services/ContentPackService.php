<?php

namespace App\Services;

use App\Models\ContentPack;
use App\Models\ContentPackChangeRequest;
use Illuminate\Support\Facades\DB;

class ContentPackService
{
    /**
     * Create or update a pending change request for a published pack.
     * Returns the data to include in the JSON response.
     */
    public function upsertChangeRequest(ContentPack $pack, string $field, mixed $value): array
    {
        $changeRequest = null;

        DB::transaction(function () use ($pack, $field, $value, &$changeRequest) {
            $changeRequest = ContentPackChangeRequest::where('content_pack_id', $pack->id)
                ->whereIn('status', ['pending', 'has_remarks'])
                ->lockForUpdate()
                ->first();

            $currentValue = $pack->{$field};
            $isSameAsCurrent = match ($field) {
                'price' => (int) $value === (int) $currentValue,
                'description' => (string) ($value ?? '') === (string) ($currentValue ?? ''),
                default => (string) $value === (string) $currentValue,
            };

            if ($changeRequest) {
                $changedFields = $changeRequest->changed_fields ?? [];

                if ($isSameAsCurrent) {
                    // Remove this field from the pending request
                    $changedFields = array_values(array_filter($changedFields, fn ($f) => $f !== $field));
                    if (empty($changedFields)) {
                        $changeRequest->delete();
                        $changeRequest = null;

                        return;
                    }
                    // Also remove from flagged if it was flagged
                    $flaggedFields = array_values(array_filter($changeRequest->flagged_fields ?? [], fn ($f) => $f !== $field));
                    $fieldComments = array_filter($changeRequest->field_comments ?? [], fn ($k) => $k !== $field, ARRAY_FILTER_USE_KEY);
                    $changeRequest->update([
                        'changed_fields' => $changedFields,
                        "pending_{$field}" => null,
                        'flagged_fields' => $flaggedFields ?: null,
                        'field_comments' => $fieldComments ?: null,
                        'status' => 'pending',
                    ]);
                } else {
                    if (! in_array($field, $changedFields)) {
                        $changedFields[] = $field;
                    }
                    // Remove this field from flagged (user fixed it)
                    $flaggedFields = array_values(array_filter($changeRequest->flagged_fields ?? [], fn ($f) => $f !== $field));
                    $fieldComments = array_filter($changeRequest->field_comments ?? [], fn ($k) => $k !== $field, ARRAY_FILTER_USE_KEY);
                    // If no more flagged fields, reset status to pending
                    $newStatus = empty($flaggedFields) ? 'pending' : $changeRequest->status;
                    $changeRequest->update([
                        'changed_fields' => $changedFields,
                        "pending_{$field}" => $value,
                        'flagged_fields' => $flaggedFields ?: null,
                        'field_comments' => $fieldComments ?: null,
                        'status' => $newStatus,
                    ]);
                }
            } else {
                if ($isSameAsCurrent) {
                    return; // nothing to do
                }
                $changeRequest = ContentPackChangeRequest::create([
                    'content_pack_id' => $pack->id,
                    'changed_fields' => [$field],
                    "pending_{$field}" => $value,
                    'status' => 'pending',
                ]);
            }
        });

        if ($changeRequest === null) {
            // Pending request was removed (user reverted to original value)
            return [
                $field => $value,
                'pending' => false,
                'pending_change' => null,
            ];
        }

        return [
            $field => $value,
            'pending' => true,
            'pending_change' => [
                'changed_fields' => $changeRequest->changed_fields,
                'pending_title' => $changeRequest->pending_title,
                'pending_description' => $changeRequest->pending_description,
                'pending_price' => $changeRequest->pending_price,
            ],
        ];
    }
}
