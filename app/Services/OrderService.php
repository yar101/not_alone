<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\MessageSent;
use App\Events\NewMessageReceived;
use App\Events\NewNotification;
use App\Events\OrderChanged;
use App\Events\OrderStatusChanged;
use App\Jobs\CompleteOrderJob;
use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\User;
use App\Models\UserIdolTrial;
use App\Notifications\OrderAcceptedNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\OrderPaidNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    // ── Public transitions (user actions) ────────────────────────────────────

    public function createOrder(User $customer, User $idol, array $requestedServices): array
    {
        if ($customer->id === $idol->id) {
            throw new \Exception('Нельзя заказать услуги у самого себя');
        }

        $serviceIds = collect($requestedServices)->pluck('id');
        $quantityMap = collect($requestedServices)->keyBy('id');

        $services = Service::whereIn('id', $serviceIds)
            ->where('user_id', $idol->id)
            ->where('is_active', true)
            ->with(['category', 'timeUnit'])
            ->get();

        if ($services->count() !== $serviceIds->unique()->count()) {
            throw new \Exception('Некоторые услуги недоступны');
        }

        if ($idol->isActiveBanned()) {
            throw new \Exception('Пользователь недоступен');
        }

        if (ChatBlock::active()->where('blocker_id', $idol->id)->where('blocked_id', $customer->id)->exists()) {
            throw new \Exception('Вы заблокированы этим пользователем');
        }

        $trialServicesCount = $services->where('is_trial', true)->count();
        if ($trialServicesCount > 1) {
            throw new \Exception('Нельзя заказать более одной бесплатной услуги одновременно');
        }

        return DB::transaction(function () use ($customer, $idol, $services, $quantityMap, $trialServicesCount) {
            $hasUsedTrial = false;
            if ($trialServicesCount > 0) {
                $hasUsedTrial = UserIdolTrial::where('user_id', $customer->id)
                    ->where('idol_id', $idol->id)
                    ->lockForUpdate()
                    ->exists();

                if ($hasUsedTrial) {
                    throw new \Exception('Вы уже использовали бесплатный первый заказ у этого пользователя');
                }
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'idol_id' => $idol->id,
                'status' => OrderStatus::Pending,
            ]);
            $order->logStatusChange(null, OrderStatus::Pending->value, 'user', $customer->id);

            foreach ($services as $service) {
                $qty = (int) ($quantityMap[$service->id]['quantity'] ?? 1);
                $price = ($service->is_trial && ! $hasUsedTrial) ? 0 : $service->price;
                $order->items()->create(['service_id' => $service->id, 'quantity' => $qty, 'price' => $price]);
            }

            if ($trialServicesCount > 0 && ! $hasUsedTrial) {
                UserIdolTrial::create([
                    'user_id' => $customer->id,
                    'idol_id' => $idol->id,
                    'order_id' => $order->id,
                ]);
            }

            $conversation = Conversation::create(['order_id' => $order->id]);
            $conversation->participants()->createMany([
                ['user_id' => $customer->id],
                ['user_id' => $idol->id],
            ]);

            $order->update(['conversation_id' => $conversation->id]);

            $msg = $conversation->messages()->create([
                'sender_id' => $customer->id,
                'body' => '',
                'type' => 'system',
                'metadata' => [
                    'event' => 'order_created',
                    'order_id' => $order->id,
                    'services' => $services->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'price' => ($s->is_trial && ! $hasUsedTrial) ? 0 : $s->price,
                        'time_unit' => $s->timeUnit?->name,
                        'quantity' => (int) ($quantityMap[$s->id]['quantity'] ?? 1),
                    ])->values()->all(),
                ],
            ]);

            $conversation->touch();

            DB::afterCommit(function () use ($msg, $conversation, $order) {
                $msg->load('sender');
                $this->safeBroadcast(new MessageSent($msg));
                foreach ($conversation->participants as $participant) {
                    $this->safeBroadcast(new NewMessageReceived(
                        $participant->user_id,
                        $conversation->id,
                        $msg,
                        $order->id,
                    ));
                }
            });

            $order->load(['customer', 'idol', 'cancelledBy', 'items.service.timeUnit']);

            return [$order, $conversation];
        });
    }

    public function accept(Order $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== OrderStatus::Pending) {
                throw new \DomainException("Невозможно принять заказ в статусе {$lockedOrder->status->label()}");
            }

            $lockedOrder->logStatusChange(OrderStatus::Pending->value, OrderStatus::Accepted->value, 'user', $actor->id);
            $lockedOrder->update(['status' => OrderStatus::Accepted]);

            $this->broadcastSystemMessage($lockedOrder, [
                'sender_id' => $actor->id,
                'body' => '',
                'type' => 'system',
                'metadata' => [
                    'event' => 'order_accepted',
                    'idol_id' => $actor->id,
                    'idol_name' => $actor->name,
                    'idol_gender' => $actor->gender,
                ],
            ]);

            $this->broadcastStatusChanged($lockedOrder, 'accepted');
            $this->broadcastOrderChanged($lockedOrder);

            DB::afterCommit(function () use ($lockedOrder) {
                $lockedOrder->customer->notify(new OrderAcceptedNotification($lockedOrder));
                $this->safeBroadcast(new NewNotification('private', $lockedOrder->customer_id));
            });
        });
    }

    public function pay(Order $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== OrderStatus::Accepted) {
                throw new \DomainException("Невозможно оплатить заказ в статусе {$lockedOrder->status->label()}");
            }

            $lockedOrder->logStatusChange(OrderStatus::Accepted->value, OrderStatus::Paid->value, 'user', $actor->id);
            $lockedOrder->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

            $this->broadcastSystemMessage($lockedOrder, [
                'sender_id' => $actor->id,
                'body' => '',
                'type' => 'system',
                'metadata' => [
                    'event' => 'order_paid',
                    'customer_id' => $actor->id,
                    'customer_name' => $actor->name,
                ],
            ]);

            $this->broadcastStatusChanged($lockedOrder, 'paid');
            $this->broadcastOrderChanged($lockedOrder);

            DB::afterCommit(function () use ($lockedOrder) {
                $lockedOrder->idol->notify(new OrderPaidNotification($lockedOrder));
                $this->safeBroadcast(new NewNotification('private', $lockedOrder->idol_id));

                // Auto-complete after delay from settings
                $delayHours = (float) PlatformSetting::get('order_auto_complete_delay', 72);
                CompleteOrderJob::dispatch($lockedOrder)->delay(now()->addSeconds((int) ($delayHours * 3600)));
            });
        });
    }

    public function cancel(Order $order, User $actor, string $reason): void
    {
        DB::transaction(function () use ($order, $actor, $reason) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->status, [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Paid])) {
                throw new \DomainException("Невозможно отменить заказ в статусе {$lockedOrder->status->label()}");
            }

            $from = $lockedOrder->status->value;
            $lockedOrder->logStatusChange($from, OrderStatus::Cancelled->value, 'user', $actor->id, $reason);
            $lockedOrder->update([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $actor->id,
            ]);

            UserIdolTrial::where('order_id', $lockedOrder->id)->delete();

            $this->broadcastSystemMessage($lockedOrder, [
                'sender_id' => $actor->id,
                'body' => '',
                'type' => 'system',
                'metadata' => [
                    'event' => 'order_cancelled',
                    'cancelled_by' => $actor->id,
                    'cancelled_by_name' => $actor->name,
                    'cancel_reason' => $reason,
                ],
            ]);

            $this->broadcastStatusChanged($lockedOrder, 'cancelled',
                cancelledBy: $actor->id,
                cancelledByName: $actor->name,
                cancelReason: $reason,
            );
            $this->broadcastOrderChanged($lockedOrder);

            $otherId = $lockedOrder->customer_id === $actor->id ? $lockedOrder->idol_id : $lockedOrder->customer_id;
            $other = User::find($otherId);

            DB::afterCommit(function () use ($other, $otherId, $lockedOrder) {
                $other?->notify(new OrderCancelledNotification($lockedOrder));
                $this->safeBroadcast(new NewNotification('private', $otherId));
            });
        });
    }

    public function confirmCompletion(Order $order, User $actor): array
    {
        return DB::transaction(function () use ($order, $actor) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status === OrderStatus::Completed) {
                return [
                    'confirmed' => true,
                    'completion_confirmed_by_idol' => (bool) $lockedOrder->completion_confirmed_by_idol,
                    'completion_confirmed_by_customer' => (bool) $lockedOrder->completion_confirmed_by_customer,
                ];
            }

            if ($lockedOrder->status !== OrderStatus::Paid) {
                throw new \DomainException("Невозможно подтвердить выполнение заказа в статусе {$lockedOrder->status->label()}");
            }

            $isIdol = $lockedOrder->idol_id === $actor->id;
            $field = $isIdol ? 'completion_confirmed_by_idol' : 'completion_confirmed_by_customer';
            $lockedOrder->update([$field => true]);
            $lockedOrder->refresh();

            $event = $isIdol ? 'completion_confirmed_by_idol' : 'completion_confirmed_by_customer';
            $this->broadcastSystemMessage($lockedOrder, [
                'sender_id' => $actor->id,
                'body' => '',
                'type' => 'system',
                'metadata' => ['event' => $event, 'actor_name' => $actor->name],
            ]);

            // Customer confirmation completes immediately; Idol confirmation waits for customer
            if (! $isIdol || ($lockedOrder->completion_confirmed_by_idol && $lockedOrder->completion_confirmed_by_customer)) {
                $this->complete($lockedOrder, 'user', $actor->id);
            } else {
                $this->broadcastOrderChanged($lockedOrder);
            }

            return [
                'confirmed' => true,
                'completion_confirmed_by_idol' => (bool) $lockedOrder->completion_confirmed_by_idol,
                'completion_confirmed_by_customer' => (bool) $lockedOrder->completion_confirmed_by_customer,
            ];
        });
    }

    public function dispute(Order $order, User $actor, string $reason, string $details): void
    {
        DB::transaction(function () use ($order, $actor, $reason, $details) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== OrderStatus::Paid && $lockedOrder->status !== OrderStatus::Completed) {
                throw new \DomainException("Невозможно открыть спор по заказу в статусе {$lockedOrder->status->label()}");
            }

            if ($lockedOrder->completed_at && $lockedOrder->completed_at->lt(now()->subHour())) {
                throw new \DomainException('Время для оспаривания истекло. Спор можно открыть в течение 1 часа после завершения заказа.');
            }

            if ($lockedOrder->disputes()->exists()) {
                throw new \DomainException('По этому заказу уже открыт спор.');
            }

            OrderDispute::create([
                'order_id' => $lockedOrder->id,
                'customer_id' => $actor->id,
                'reason' => $reason,
                'details' => $details,
            ]);

            $fromStatus = $lockedOrder->status->value;
            $lockedOrder->logStatusChange($fromStatus, OrderStatus::Disputed->value, 'user', $actor->id, $reason);
            $lockedOrder->update(['status' => OrderStatus::Disputed]);

            $this->broadcastStatusChanged($lockedOrder, 'disputed');
            $this->broadcastOrderChanged($lockedOrder);
        });
    }

    public function addItem(Order $order, int $serviceId, User $actor): void
    {
        DB::transaction(function () use ($order, $serviceId) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== OrderStatus::Pending) {
                throw new \DomainException('Добавлять услуги можно только к созданным заказам.');
            }

            $service = Service::where('id', $serviceId)
                ->where('user_id', $lockedOrder->idol_id)
                ->where('is_active', true)
                ->where('status', 'approved')
                ->with('timeUnit:id,name')
                ->firstOrFail();

            $existing = $lockedOrder->items()->where('service_id', $service->id)->first();
            if ($existing) {
                $existing->increment('quantity');
            } else {
                $lockedOrder->items()->create(['service_id' => $service->id, 'quantity' => 1, 'price' => $service->price]);
            }

            $lockedOrder->touch();

            if ($lockedOrder->conversation_id) {
                $lockedOrder->load(['items.service.timeUnit']);
                $allItems = $lockedOrder->items->map(fn ($item) => [
                    'id' => $item->service?->id,
                    'name' => $item->service?->name,
                    'price' => $item->price ?? $item->service?->price,
                    'time_unit' => $item->service?->timeUnit?->name,
                    'quantity' => $item->quantity ?? 1,
                ])->values()->all();

                $this->broadcastSystemMessage($lockedOrder, [
                    'sender_id' => null,
                    'body' => '',
                    'type' => 'system',
                    'metadata' => [
                        'event' => 'item_added',
                        'services' => $allItems,
                    ],
                ]);
            }

            $this->broadcastOrderChanged($lockedOrder);
        });
    }

    // ── Admin transition ──────────────────────────────────────────────────────

    public function adminTransition(Order $order, OrderStatus $to, int $adminId, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $to, $adminId, $note) {
            $lockedOrder = Order::lockForUpdate()->find($order->id);
            if (! $lockedOrder) {
                return;
            }

            $from = $lockedOrder->status->value;
            $attrs = ['status' => $to];

            switch ($to) {
                case OrderStatus::Paid:
                    $isNewPaid = $lockedOrder->status !== OrderStatus::Paid;
                    $attrs['paid_at'] = $lockedOrder->paid_at ?? now();

                    if ($lockedOrder->conversation_id) {
                        $lockedOrder->conversation->messages()->create([
                            'sender_id' => null,
                            'body' => '',
                            'type' => 'system',
                            'metadata' => ['event' => 'order_paid', 'by_admin' => true],
                        ]);
                        $lockedOrder->conversation->touch();
                    }

                    DB::afterCommit(function () use ($lockedOrder, $isNewPaid) {
                        $lockedOrder->idol->notify(new OrderPaidNotification($lockedOrder));
                        $this->safeBroadcast(new NewNotification('private', $lockedOrder->idol_id));

                        if ($isNewPaid) {
                            $delayHours = (float) PlatformSetting::get('order_auto_complete_delay', 72);
                            CompleteOrderJob::dispatch($lockedOrder)->delay(now()->addSeconds((int) ($delayHours * 3600)));
                        }
                    });
                    break;

                case OrderStatus::Completed:
                    $attrs['completed_at'] = $lockedOrder->completed_at ?? now();

                    IdolRatingService::adjust($lockedOrder->idol, 'order_completed');

                    if ($lockedOrder->conversation_id) {
                        $lockedOrder->conversation->messages()->create([
                            'sender_id' => null,
                            'body' => '',
                            'type' => 'system',
                            'metadata' => ['event' => 'order_completed', 'by_admin' => true],
                        ]);
                        $lockedOrder->conversation->touch();
                    }

                    DB::afterCommit(function () use ($lockedOrder) {
                        $lockedOrder->customer->notify(new OrderCompletedNotification($lockedOrder));
                        $lockedOrder->idol->notify(new OrderCompletedNotification($lockedOrder));
                        $this->safeBroadcast(new NewNotification('private', $lockedOrder->customer_id));
                        $this->safeBroadcast(new NewNotification('private', $lockedOrder->idol_id));
                    });
                    break;

                case OrderStatus::Cancelled:
                    if (! $lockedOrder->cancelled_by) {
                        $attrs['cancelled_by'] = null;
                    }

                    if ($lockedOrder->conversation_id) {
                        $lockedOrder->conversation->messages()->create([
                            'sender_id' => null,
                            'body' => '',
                            'type' => 'system',
                            'metadata' => [
                                'event' => 'order_cancelled',
                                'cancel_reason' => $note ?? 'Отменён администратором',
                                'by_admin' => true,
                            ],
                        ]);
                        $lockedOrder->conversation->touch();
                    }

                    UserIdolTrial::where('order_id', $lockedOrder->id)->delete();
                    break;

                case OrderStatus::Refunded:
                    break;

                default:
                    break;
            }

            $lockedOrder->logStatusChange($from, $to->value, 'admin', $adminId, $note);
            $lockedOrder->update($attrs);

            $this->broadcastStatusChanged($lockedOrder, $to->value);
            $this->broadcastOrderChanged($lockedOrder);
        });
    }

    // ── Scheduled auto-complete ───────────────────────────────────────────────

    public function autoCompleteOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::lockForUpdate()->find($order->id);
            if (! $lockedOrder || $lockedOrder->status !== OrderStatus::Paid) {
                return;
            }

            $delayHours = (int) PlatformSetting::get('order_auto_complete_delay', 72);
            $this->complete($lockedOrder, 'system', 0, "Auto-completed after {$delayHours}h timeout");
        });
    }

    // ── Order formatter (used by controllers & command) ───────────────────────

    public function formatOrder(Order $order, int $userId): array
    {
        $isCustomer = $order->customer_id === $userId;

        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'cancel_reason' => $order->cancel_reason,
            'cancelled_by' => $order->cancelled_by,
            'cancelled_by_name' => $order->cancelledBy?->name,
            'conversation_id' => $order->conversation_id,
            'created_at' => $order->created_at->toISOString(),
            'paid_at' => $order->paid_at?->toISOString(),
            'completed_at' => $order->completed_at?->toISOString(),
            'is_customer' => $isCustomer,
            'completion_confirmed_by_idol' => (bool) $order->completion_confirmed_by_idol,
            'completion_confirmed_by_customer' => (bool) $order->completion_confirmed_by_customer,
            'customer' => [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'avatar_url' => $order->customer->avatar_url,
                'active_frame' => $order->customer->activeFrame,
                'gender' => $order->customer->gender,
            ],
            'idol' => [
                'id' => $order->idol->id,
                'name' => $order->idol->name,
                'avatar_url' => $order->idol->avatar_url,
                'active_frame' => $order->idol->activeFrame,
                'gender' => $order->idol->gender,
            ],
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity ?? 1,
                'service' => $item->service ? [
                    'id' => $item->service->id,
                    'name' => $item->service->name,
                    'price' => $item->price ?? $item->service->price,
                    'time_unit' => $item->service->timeUnit?->name,
                ] : null,
            ])->values()->all(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function complete(Order $order, string $actorType, int $actorId, ?string $note = null): void
    {
        if ($order->status === OrderStatus::Completed) {
            return;
        }

        $old = $order->status->value;
        $order->logStatusChange($old, OrderStatus::Completed->value, $actorType, $actorId, $note);
        $order->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);

        IdolRatingService::adjust($order->idol, 'order_completed');

        $this->broadcastSystemMessage($order, [
            'sender_id' => null,
            'body' => '',
            'type' => 'system',
            'metadata' => [
                'event' => $actorType === 'system' ? 'order_auto_completed' : 'order_completed',
                'order_id' => $order->id,
            ],
        ]);

        $this->broadcastStatusChanged($order, 'completed');
        $this->broadcastOrderChanged($order);

        DB::afterCommit(function () use ($order) {
            \App\Events\OrderCompletedEvent::dispatch($order);
            $order->customer->notify(new OrderCompletedNotification($order));
            $order->idol->notify(new OrderCompletedNotification($order));
            $this->safeBroadcast(new NewNotification('private', $order->customer_id));
            $this->safeBroadcast(new NewNotification('private', $order->idol_id));
        });
    }

    private function broadcastOrderChanged(Order $order): void
    {
        DB::afterCommit(function () use ($order) {
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
        });
    }

    private function broadcastStatusChanged(
        Order $order,
        string $status,
        ?int $cancelledBy = null,
        ?string $cancelledByName = null,
        ?string $cancelReason = null,
    ): void {
        if (! $order->conversation_id) {
            return;
        }

        DB::afterCommit(function () use ($order, $status, $cancelledBy, $cancelledByName, $cancelReason) {
            $this->safeBroadcast(new OrderStatusChanged(
                conversationId: $order->conversation_id,
                orderId: $order->id,
                status: $status,
                cancelledBy: $cancelledBy,
                cancelledByName: $cancelledByName,
                cancelReason: $cancelReason,
                paidAt: $order->paid_at?->toISOString(),
                completedAt: $order->completed_at?->toISOString(),
                autoCompleteAt: $order->paid_at ? $order->paid_at->copy()->addSeconds((int) ((float) PlatformSetting::get('order_auto_complete_delay', 72) * 3600))->toISOString() : null,
                confirmedByIdol: (bool) $order->completion_confirmed_by_idol,
                confirmedByCustomer: (bool) $order->completion_confirmed_by_customer,
            ));
        });
    }

    private function broadcastSystemMessage(Order $order, array $messageAttributes): void
    {
        if (! $order->conversation_id) {
            return;
        }

        $msg = $order->conversation->messages()->create($messageAttributes);
        $order->conversation->touch();

        DB::afterCommit(function () use ($msg, $order) {
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
        });
    }

    private function safeBroadcast(mixed $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed: '.$e->getMessage());
        }
    }
}
