<?php

namespace App\Traits;

trait NotificationTypes
{
    protected array $SERVICE_TYPES = [
        'idol_approved', 'idol_rejected', 'admin_broadcast', 'low_rating_warning', 'admin_rating',
        'review_dispute_approved', 'review_dispute_rejected', 'content_pack_approved',
        'content_pack_remarks', 'content_pack_rejected', 'content_pack_change_approved',
        'content_pack_change_remarks', 'content_pack_change_rejected',
        'service_approved', 'service_rejected', 'service_remarks',
        'service_change_approved', 'service_change_remarks', 'service_change_rejected',
        'test', 'new_review',
    ];

    protected array $ORDER_TYPES = ['order_created', 'order_accepted', 'order_cancelled', 'order_paid', 'order_completed'];

    protected array $MESSAGE_TYPES = ['new_message'];

    protected array $FOLLOW_TYPES = ['new_post', 'new_service', 'new_content_pack'];

    protected array $TYPE_MAP = [
        'idol_approved' => \App\Notifications\IdolApprovedNotification::class,
        'idol_rejected' => \App\Notifications\IdolRejectedNotification::class,
        'low_rating_warning' => \App\Notifications\LowRatingWarningNotification::class,
        'admin_rating' => \App\Notifications\AdminRatingNotification::class,
        'review_dispute_approved' => \App\Notifications\ReviewDisputeApprovedNotification::class,
        'review_dispute_rejected' => \App\Notifications\ReviewDisputeRejectedNotification::class,
        'content_pack_approved' => \App\Notifications\ContentPackApprovedNotification::class,
        'content_pack_remarks' => \App\Notifications\ContentPackRemarksNotification::class,
        'content_pack_rejected' => \App\Notifications\ContentPackRejectedNotification::class,
        'content_pack_change_approved' => \App\Notifications\ContentPackChangeApprovedNotification::class,
        'content_pack_change_remarks' => \App\Notifications\ContentPackChangeRemarksNotification::class,
        'content_pack_change_rejected' => \App\Notifications\ContentPackChangeRejectedNotification::class,
        'service_approved' => \App\Notifications\ServiceApprovedNotification::class,
        'service_rejected' => \App\Notifications\ServiceRejectedNotification::class,
        'service_remarks' => \App\Notifications\ServiceRemarksNotification::class,
        'service_change_approved' => \App\Notifications\ServiceChangeApprovedNotification::class,
        'service_change_remarks' => \App\Notifications\ServiceChangeRemarksNotification::class,
        'service_change_rejected' => \App\Notifications\ServiceChangeRejectedNotification::class,
        'test' => \App\Notifications\TestNotification::class,
        'new_review' => \App\Notifications\NewReviewNotification::class,
        'order_created' => \App\Notifications\OrderCreatedNotification::class,
        'order_accepted' => \App\Notifications\OrderAcceptedNotification::class,
        'order_cancelled' => \App\Notifications\OrderCancelledNotification::class,
        'order_paid' => \App\Notifications\OrderPaidNotification::class,
        'order_completed' => \App\Notifications\OrderCompletedNotification::class,
        'new_message' => \App\Notifications\NewMessageNotification::class,
        'new_post' => \App\Notifications\NewPostNotification::class,
        'new_service' => \App\Notifications\NewServiceNotification::class,
        'new_content_pack' => \App\Notifications\NewContentPackNotification::class,
        'strike' => \App\Notifications\UserStrikeNotification::class,
    ];

    protected function getClassesForTypes(array $types): array
    {
        $classes = [];
        foreach ($types as $type) {
            if (isset($this->TYPE_MAP[$type])) {
                $classes[] = $this->TYPE_MAP[$type];
            }
        }

        return $classes;
    }
}
