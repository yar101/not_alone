<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function __construct($resource, public bool $isOwner = false, public bool $hasUsedTrial = false)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->id,
            'name_ru' => $this->getTranslation('name', 'ru'),
            'name_en' => $this->getTranslation('name', 'en', false) ?: null,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'is_trial' => $this->isOwner ? $this->is_trial : ($this->hasUsedTrial ? false : $this->is_trial),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'category_id' => $this->category_id,
            'time_unit' => $this->whenLoaded('timeUnit', fn () => [
                'id' => $this->timeUnit->id,
                'name_ru' => $this->timeUnit->getTranslation('name', 'ru'),
                'name_en' => $this->timeUnit->getTranslation('name', 'en', false) ?: null,
            ]),
        ];

        if ($this->isOwner) {
            $base['latest_review'] = $this->whenLoaded('latestReview', fn () => $this->latestReview ? [
                'decision' => $this->latestReview->decision,
                'flagged_fields' => $this->latestReview->flagged_fields ?? [],
                'field_comments' => $this->latestReview->field_comments ?? [],
            ] : null);

            $base['pending_change'] = $this->whenLoaded('pendingChangeRequest', fn () => $this->pendingChangeRequest ? [
                'changed_fields' => $this->pendingChangeRequest->changed_fields,
                'pending_name' => $this->pendingChangeRequest->pending_name,
                'pending_price' => $this->pendingChangeRequest->pending_price,
                'pending_category' => $this->pendingChangeRequest->pendingCategory ? [
                    'id' => $this->pendingChangeRequest->pendingCategory->id,
                    'name_ru' => $this->pendingChangeRequest->pendingCategory->getTranslation('name', 'ru'),
                ] : null,
                'pending_time_unit' => $this->pendingChangeRequest->pendingTimeUnit ? [
                    'id' => $this->pendingChangeRequest->pendingTimeUnit->id,
                    'name_ru' => $this->pendingChangeRequest->pendingTimeUnit->getTranslation('name', 'ru'),
                ] : null,
                'status' => $this->pendingChangeRequest->status,
                'flagged_fields' => $this->pendingChangeRequest->flagged_fields ?? [],
                'field_comments' => $this->pendingChangeRequest->field_comments ?? [],
                'admin_comment' => $this->pendingChangeRequest->admin_comment,
            ] : null);
        }

        return $base;
    }
}
