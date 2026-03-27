<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\NewNotification;
use App\Events\OrderChanged;
use App\Events\OrderStatusChanged;
use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Notifications\OrderAcceptedNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'idol_id'              => 'required|exists:users,id',
            'services'             => 'required|array|min:1',
            'services.*.id'        => 'required|integer|exists:services,id',
            'services.*.quantity'  => 'required|integer|min:1|max:99',
        ]);

        $user  = $request->user();
        $idol  = User::findOrFail($request->idol_id);

        $serviceIds  = collect($request->services)->pluck('id');
        $quantityMap = collect($request->services)->keyBy('id');

        // Verify all services belong to this idol and are active
        $services = Service::whereIn('id', $serviceIds)
            ->where('user_id', $idol->id)
            ->where('is_active', true)
            ->with(['category', 'timeUnit'])
            ->get();

        if ($services->count() !== $serviceIds->unique()->count()) {
            return response()->json(['error' => 'Некоторые услуги недоступны'], 422);
        }

        if (ChatBlock::active()->where('blocker_id', $idol->id)->where('blocked_id', $user->id)->exists()) {
            return response()->json(['error' => 'Вы заблокированы этим пользователем'], 422);
        }

        // Create order
        $order = Order::create([
            'customer_id' => $user->id,
            'idol_id'     => $idol->id,
            'status'      => 'pending',
        ]);

        // Create order items with quantity
        foreach ($services as $service) {
            $qty = (int) ($quantityMap[$service->id]['quantity'] ?? 1);
            $order->items()->create(['service_id' => $service->id, 'quantity' => $qty]);
        }

        // Create dedicated conversation
        $conversation = Conversation::create(['order_id' => $order->id]);
        $conversation->participants()->createMany([
            ['user_id' => $user->id],
            ['user_id' => $idol->id],
        ]);

        // Link conversation back to order
        $order->update(['conversation_id' => $conversation->id]);

        // System message with ordered services list
        $conversation->messages()->create([
            'sender_id' => $user->id,
            'body'      => '',
            'type'      => 'system',
            'metadata'  => [
                'event'    => 'order_created',
                'order_id' => $order->id,
                'services' => $services->map(fn($s) => [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'price'     => $s->price,
                    'time_unit' => $s->timeUnit?->name,
                    'quantity'  => (int) ($quantityMap[$s->id]['quantity'] ?? 1),
                ])->values()->all(),
            ],
        ]);

        $conversation->touch();

        // Broadcast to orders channels
        $order->load(['customer', 'idol', 'cancelledBy', 'items.service.timeUnit']);
        broadcast(new OrderChanged($idol->id, $this->formatOrder($order, $idol->id), 'created'));
        broadcast(new OrderChanged($user->id, $this->formatOrder($order, $user->id), 'created'));

        // Notify idol
        $idol->notify(new OrderCreatedNotification($order));
        broadcast(new NewNotification('private', $idol->id));

        return response()->json([
            'order_id'        => $order->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    public function accept(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($order->idol_id === $user->id, 403);
        abort_unless($order->status === 'pending', 422);

        $order->update(['status' => 'accepted']);

        if ($order->conversation_id) {
            $msg = $order->conversation->messages()->create([
                'sender_id' => $user->id,
                'body'      => '',
                'type'      => 'system',
                'metadata'  => [
                    'event'       => 'order_accepted',
                    'idol_id'     => $user->id,
                    'idol_name'   => $user->name,
                    'idol_gender' => $user->gender,
                ],
            ]);
            $order->conversation->touch();
            $msg->load('sender');
            broadcast(new MessageSent($msg));
            broadcast(new OrderStatusChanged($order->conversation_id, $order->id, 'accepted'));
        }

        // Broadcast to orders channels
        $order->load(['customer', 'idol', 'cancelledBy', 'items.service.timeUnit']);
        broadcast(new OrderChanged($order->idol_id,     $this->formatOrder($order, $order->idol_id),     'updated'));
        broadcast(new OrderChanged($order->customer_id, $this->formatOrder($order, $order->customer_id), 'updated'));

        $order->customer->notify(new OrderAcceptedNotification($order));
        broadcast(new NewNotification('private', $order->customer_id));

        return response()->json(['status' => 'accepted']);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $order->customer_id === $user->id || $order->idol_id === $user->id,
            403
        );
        abort_unless(in_array($order->status, ['pending', 'accepted']), 422);

        $request->validate(['cancel_reason' => 'required|string|max:1000']);

        $order->update([
            'status'        => 'cancelled',
            'cancel_reason' => $request->cancel_reason,
            'cancelled_by'  => $user->id,
        ]);

        // System message in conversation
        if ($order->conversation_id) {
            $msg = $order->conversation->messages()->create([
                'sender_id' => $user->id,
                'body'      => '',
                'type'      => 'system',
                'metadata'  => [
                    'event'              => 'order_cancelled',
                    'cancelled_by'       => $user->id,
                    'cancelled_by_name'  => $user->name,
                    'cancel_reason'      => $request->cancel_reason,
                ],
            ]);
            $order->conversation->touch();
            $msg->load('sender');
            broadcast(new MessageSent($msg));
            broadcast(new OrderStatusChanged(
                $order->conversation_id,
                $order->id,
                'cancelled',
                $user->id,
                $user->name,
                $request->cancel_reason,
            ));
        }

        // Broadcast to orders channels
        $order->load(['customer', 'idol', 'cancelledBy', 'items.service.timeUnit']);
        broadcast(new OrderChanged($order->idol_id,     $this->formatOrder($order, $order->idol_id),     'updated'));
        broadcast(new OrderChanged($order->customer_id, $this->formatOrder($order, $order->customer_id), 'updated'));

        // Notify the other party
        $otherId = $order->customer_id === $user->id ? $order->idol_id : $order->customer_id;
        $other   = User::find($otherId);
        $other?->notify(new OrderCancelledNotification($order));
        broadcast(new NewNotification('private', $otherId));

        return response()->json(['status' => 'cancelled']);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::where('customer_id', $user->id)
            ->orWhere('idol_id', $user->id)
            ->with([
                'customer',
                'idol',
                'cancelledBy',
                'items.service.category',
                'items.service.timeUnit',
            ])
            ->latest()
            ->get()
            ->map(fn(Order $order) => $this->formatOrder($order, $user->id));

        return response()->json(['orders' => $orders]);
    }

    private function formatOrder(Order $order, int $userId): array
    {
        $isCustomer = $order->customer_id === $userId;

        return [
            'id'              => $order->id,
            'status'          => $order->status,
            'cancel_reason'   => $order->cancel_reason,
            'cancelled_by'      => $order->cancelled_by,
            'cancelled_by_name' => $order->cancelledBy?->name,
            'conversation_id' => $order->conversation_id,
            'created_at'      => $order->created_at->toISOString(),
            'is_customer'     => $isCustomer,
            'customer'        => [
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
                'id'        => $item->id,
                'service'   => $item->service ? [
                    'id'        => $item->service->id,
                    'name'      => $item->service->name,
                    'price'     => $item->service->price,
                    'time_unit' => $item->service->timeUnit?->name,
                ] : null,
            ])->values()->all(),
        ];
    }
}
