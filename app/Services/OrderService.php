<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\MessageSent;
use App\Events\NewMessageReceived;
use App\Events\NewNotification;
use App\Events\OrderChanged;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\User;
use App\Notifications\OrderAcceptedNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\OrderPaidNotification;

class OrderService
{
    // ── Public transitions (user actions) ────────────────────────────────────

    public function accept(Order $order, User $actor): void
    {
        $order->logStatusChange(OrderStatus::Pending->value, OrderStatus::Accepted->value, 'user', $actor->id);
        $order->update(['status' => OrderStatus::Accepted]);

        $this->broadcastSystemMessage($order, [
            'sender_id' => $actor->id,
            'body'      => '',
            'type'      => 'system',
            'metadata'  => [
                'event'       => 'order_accepted',
                'idol_id'     => $actor->id,
                'idol_name'   => $actor->name,
                'idol_gender' => $actor->gender,
            ],
        ]);

        $this->broadcastStatusChanged($order, 'accepted');
        $this->broadcastOrderChanged($order);

        $order->customer->notify(new OrderAcceptedNotification($order));
        $this->safeBroadcast(new NewNotification('private', $order->customer_id));
    }

    public function pay(Order $order, User $actor): void
    {
        $order->logStatusChange(OrderStatus::Accepted->value, OrderStatus::Paid->value, 'user', $actor->id);
        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

        $this->broadcastSystemMessage($order, [
            'sender_id' => $actor->id,
            'body'      => '',
            'type'      => 'system',
            'metadata'  => [
                'event'         => 'order_paid',
                'customer_id'   => $actor->id,
                'customer_name' => $actor->name,
            ],
        ]);

        $this->broadcastStatusChanged($order, 'paid');
        $this->broadcastOrderChanged($order);

        $order->idol->notify(new OrderPaidNotification($order));
        $this->safeBroadcast(new NewNotification('private', $order->idol_id));
    }

    public function cancel(Order $order, User $actor, string $reason): void
    {
        $from = $order->status->value;
        $order->logStatusChange($from, OrderStatus::Cancelled->value, 'user', $actor->id, $reason);
        $order->update([
            'status'        => OrderStatus::Cancelled,
            'cancel_reason' => $reason,
            'cancelled_by'  => $actor->id,
        ]);

        $this->broadcastSystemMessage($order, [
            'sender_id' => $actor->id,
            'body'      => '',
            'type'      => 'system',
            'metadata'  => [
                'event'             => 'order_cancelled',
                'cancelled_by'      => $actor->id,
                'cancelled_by_name' => $actor->name,
                'cancel_reason'     => $reason,
            ],
        ]);

        $this->broadcastStatusChanged($order, 'cancelled',
            cancelledBy:     $actor->id,
            cancelledByName: $actor->name,
            cancelReason:    $reason,
        );
        $this->broadcastOrderChanged($order);

        $otherId = $order->customer_id === $actor->id ? $order->idol_id : $order->customer_id;
        $other   = User::find($otherId);
        $other?->notify(new OrderCancelledNotification($order));
        $this->safeBroadcast(new NewNotification('private', $otherId));
    }

    public function confirmCompletion(Order $order, User $actor): array
    {
        $isIdol = $order->idol_id === $actor->id;
        $field  = $isIdol ? 'completion_confirmed_by_idol' : 'completion_confirmed_by_customer';
        $order->update([$field => true]);
        $order->refresh();

        $event = $isIdol ? 'completion_confirmed_by_idol' : 'completion_confirmed_by_customer';
        $this->broadcastSystemMessage($order, [
            'sender_id' => $actor->id,
            'body'      => '',
            'type'      => 'system',
            'metadata'  => ['event' => $event, 'actor_name' => $actor->name],
        ]);

        if ($order->completion_confirmed_by_idol && $order->completion_confirmed_by_customer) {
            $this->complete($order, 'user', $actor->id);
        } else {
            $this->broadcastOrderChanged($order);
        }

        return [
            'confirmed'                        => true,
            'completion_confirmed_by_idol'     => $order->completion_confirmed_by_idol,
            'completion_confirmed_by_customer' => $order->completion_confirmed_by_customer,
        ];
    }

    public function dispute(Order $order, User $actor, string $reason, string $details): void
    {
        OrderDispute::create([
            'order_id'    => $order->id,
            'customer_id' => $actor->id,
            'reason'      => $reason,
            'details'     => $details,
        ]);

        $order->logStatusChange(OrderStatus::Completed->value, OrderStatus::Disputed->value, 'user', $actor->id, $reason);
        $order->update(['status' => OrderStatus::Disputed]);

        $this->broadcastStatusChanged($order, 'disputed');
        $this->broadcastOrderChanged($order);
    }

    // ── Admin transition ──────────────────────────────────────────────────────

    /**
     * Force any status transition from the admin panel.
     * Applies the same side effects (timestamps, notifications, broadcasts)
     * as the corresponding user-facing action so that application logic stays consistent.
     */
    public function adminTransition(Order $order, OrderStatus $to, int $adminId, ?string $note = null): void
    {
        $from  = $order->status->value;
        $attrs = ['status' => $to];

        switch ($to) {
            case OrderStatus::Paid:
                // Preserve an existing paid_at so we don't reset a running timer
                $attrs['paid_at'] = $order->paid_at ?? now();

                if ($order->conversation_id) {
                    $order->conversation->messages()->create([
                        'sender_id' => null,
                        'body'      => '',
                        'type'      => 'system',
                        'metadata'  => ['event' => 'order_paid', 'by_admin' => true],
                    ]);
                    $order->conversation->touch();
                }
                $order->idol->notify(new OrderPaidNotification($order));
                $this->safeBroadcast(new NewNotification('private', $order->idol_id));
                break;

            case OrderStatus::Completed:
                $attrs['completed_at'] = $order->completed_at ?? now();

                if ($order->conversation_id) {
                    $order->conversation->messages()->create([
                        'sender_id' => null,
                        'body'      => '',
                        'type'      => 'system',
                        'metadata'  => ['event' => 'order_completed', 'by_admin' => true],
                    ]);
                    $order->conversation->touch();
                }
                $order->customer->notify(new OrderCompletedNotification($order));
                $order->idol->notify(new OrderCompletedNotification($order));
                $this->safeBroadcast(new NewNotification('private', $order->customer_id));
                $this->safeBroadcast(new NewNotification('private', $order->idol_id));
                break;

            case OrderStatus::Cancelled:
                if (!$order->cancelled_by) {
                    $attrs['cancelled_by'] = null; // admin cancel has no specific actor
                }

                if ($order->conversation_id) {
                    $order->conversation->messages()->create([
                        'sender_id' => null,
                        'body'      => '',
                        'type'      => 'system',
                        'metadata'  => [
                            'event'         => 'order_cancelled',
                            'cancel_reason' => $note ?? 'Отменён администратором',
                            'by_admin'      => true,
                        ],
                    ]);
                    $order->conversation->touch();
                }
                break;

            case OrderStatus::Refunded:
                // Only status change + log; no dedicated notification
                break;

            default:
                break;
        }

        $order->logStatusChange($from, $to->value, 'admin', $adminId, $note);
        $order->update($attrs);

        $this->broadcastStatusChanged($order, $to->value);
        $this->broadcastOrderChanged($order);
    }

    // ── Scheduled auto-complete ───────────────────────────────────────────────

    public function autoComplete(): int
    {
        $count = 0;

        Order::where('status', OrderStatus::Paid)
            ->where('paid_at', '<=', now()->subHours(72))
            ->with(['customer', 'idol', 'conversation', 'cancelledBy', 'items.service.timeUnit'])
            ->each(function (Order $order) use (&$count) {
                $this->complete($order, 'system', 0, 'Auto-completed after 72h timeout');
                $count++;
            });

        return $count;
    }

    // ── Order formatter (used by controllers & command) ───────────────────────

    public function formatOrder(Order $order, int $userId): array
    {
        $isCustomer = $order->customer_id === $userId;

        return [
            'id'              => $order->id,
            'status'          => $order->status->value,
            'cancel_reason'   => $order->cancel_reason,
            'cancelled_by'    => $order->cancelled_by,
            'cancelled_by_name' => $order->cancelledBy?->name,
            'conversation_id' => $order->conversation_id,
            'created_at'      => $order->created_at->toISOString(),
            'paid_at'         => $order->paid_at?->toISOString(),
            'completed_at'    => $order->completed_at?->toISOString(),
            'is_customer'     => $isCustomer,
            'completion_confirmed_by_idol'     => (bool) $order->completion_confirmed_by_idol,
            'completion_confirmed_by_customer' => (bool) $order->completion_confirmed_by_customer,
            'customer' => [
                'id'         => $order->customer->id,
                'name'       => $order->customer->name,
                'avatar_url' => $order->customer->avatar_url,
            ],
            'idol' => [
                'id'         => $order->idol->id,
                'name'       => $order->idol->name,
                'avatar_url' => $order->idol->avatar_url,
                'gender'     => $order->idol->gender,
            ],
            'items' => $order->items->map(fn($item) => [
                'id'       => $item->id,
                'quantity' => $item->quantity ?? 1,
                'service'  => $item->service ? [
                    'id'        => $item->service->id,
                    'name'      => $item->service->name,
                    'price'     => $item->service->price,
                    'time_unit' => $item->service->timeUnit?->name,
                ] : null,
            ])->values()->all(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Common completion logic shared by confirmCompletion() and autoComplete().
     */
    private function complete(Order $order, string $actorType, int $actorId, ?string $note = null): void
    {
        $old = $order->status->value;
        $order->logStatusChange($old, OrderStatus::Completed->value, $actorType, $actorId, $note);
        $order->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);

        $this->broadcastSystemMessage($order, [
            'sender_id' => null,
            'body'      => '',
            'type'      => 'system',
            'metadata'  => [
                'event'    => $actorType === 'system' ? 'order_auto_completed' : 'order_completed',
                'order_id' => $order->id,
            ],
        ]);

        $this->broadcastStatusChanged($order, 'completed');
        $this->broadcastOrderChanged($order);

        $order->customer->notify(new OrderCompletedNotification($order));
        $order->idol->notify(new OrderCompletedNotification($order));
        $this->safeBroadcast(new NewNotification('private', $order->customer_id));
        $this->safeBroadcast(new NewNotification('private', $order->idol_id));
    }

    private function broadcastOrderChanged(Order $order): void
    {
        $order->loadMissing(['customer', 'idol', 'cancelledBy', 'items.service.timeUnit']);

        $this->safeBroadcast(new OrderChanged(
            $order->idol_id,
            $this->formatOrder($order, $order->idol_id),
            'updated'
        ));
        $this->safeBroadcast(new OrderChanged(
            $order->customer_id,
            $this->formatOrder($order, $order->customer_id),
            'updated'
        ));
    }

    private function broadcastStatusChanged(
        Order   $order,
        string  $status,
        ?int    $cancelledBy     = null,
        ?string $cancelledByName = null,
        ?string $cancelReason    = null,
    ): void {
        if (!$order->conversation_id) return;

        $this->safeBroadcast(new OrderStatusChanged(
            conversationId:   $order->conversation_id,
            orderId:          $order->id,
            status:           $status,
            cancelledBy:      $cancelledBy,
            cancelledByName:  $cancelledByName,
            cancelReason:     $cancelReason,
            paidAt:           $order->paid_at?->toISOString(),
            completedAt:      $order->completed_at?->toISOString(),
            confirmedByIdol:  (bool) $order->completion_confirmed_by_idol,
            confirmedByCustomer: (bool) $order->completion_confirmed_by_customer,
        ));
    }

    private function broadcastSystemMessage(Order $order, array $messageAttributes): void
    {
        if (! $order->conversation_id) return;

        $msg = $order->conversation->messages()->create($messageAttributes);
        $order->conversation->touch();
        $msg->load('sender');
        $this->safeBroadcast(new MessageSent($msg));

        $order->conversation->loadMissing('participants');
        foreach ($order->conversation->participants as $participant) {
            $this->safeBroadcast(new NewMessageReceived(
                $participant->user_id,
                $order->conversation_id,
                $msg,
                $order->id,
            ));
        }
    }

    private function safeBroadcast(mixed $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            \Log::warning('Broadcast failed: ' . $e->getMessage());
        }
    }
}
