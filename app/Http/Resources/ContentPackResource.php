<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentPackResource extends JsonResource
{
    public function __construct($resource, public bool $isOwner = false, public bool $isPurchased = false)
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
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'status' => $this->status,
            'cover_url' => $this->cover_url,
            'photos_count' => $this->whenLoaded('photos', fn () => $this->photos->count(), 0),
            'published_at' => $this->published_at?->toIso8601String(),
        ];

        // Depending on if the caller wants to show idol_id/idol_name (e.g. public view)
        // We add them dynamically if relation loaded or explicitly requested, but
        // UserProfileController always added it for public view.
        // We'll conditionally add them if they exist on the model (e.g. passed from controller)
        if ($this->relationLoaded('user') || isset($this->idol_id)) {
            $base['idol_id'] = $this->idol_id ?? $this->user_id;
            $base['idol_name'] = $this->idol_name ?? $this->user->name ?? null;
        }

        if ($this->isOwner || $this->isPurchased) {
            $base['photos'] = $this->whenLoaded('photos', fn () => $this->photos->map(fn ($ph) => [
                'id' => $ph->id,
                'url' => $ph->url,
            ])->values());
        }

        if ($this->isOwner) {
            $base['hidden_at'] = $this->hidden_at?->toIso8601String();

            $base['latest_review'] = $this->whenLoaded('latestReview', fn () => $this->latestReview ? [
                'decision' => $this->latestReview->decision,
                'flagged_fields' => $this->latestReview->flagged_fields ?? [],
                'field_comments' => $this->latestReview->field_comments ?? [],
                'flagged_photo_ids' => $this->latestReview->flagged_photo_ids ?? [],
                'photo_comments' => $this->latestReview->photo_comments ?? [],
            ] : null);

            $base['pending_change'] = $this->whenLoaded('pendingChangeRequest', fn () => $this->pendingChangeRequest ? [
                'changed_fields' => $this->pendingChangeRequest->changed_fields,
                'pending_title' => $this->pendingChangeRequest->pending_title,
                'pending_description' => $this->pendingChangeRequest->pending_description,
                'pending_price' => $this->pendingChangeRequest->pending_price,
                'status' => $this->pendingChangeRequest->status,
                'flagged_fields' => $this->pendingChangeRequest->flagged_fields ?? [],
                'field_comments' => $this->pendingChangeRequest->field_comments ?? [],
                'admin_comment' => $this->pendingChangeRequest->admin_comment,
            ] : null);
        }

        return $base;
    }
}
