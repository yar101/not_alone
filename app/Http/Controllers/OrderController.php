<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\NewNotification;
use App\Events\OrderChanged;
use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrderController extends Controller
{
    const DISPUTE_REASONS = [
        'Непристойное поведение',
        'Оскорбления',
        'Мошенничество',
        'Заказ не выполнен',
        'Угрозы',
        'Спам и навязывание',
    ];

    public function __construct(private OrderService $service) {}

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

        $services = Service::whereIn('id', $serviceIds)
            ->where('user_id', $idol->id)
            ->where('is_active', true)
            ->with(['category', 'timeUnit'])
            ->get();

        if ($services->count() !== $serviceIds->unique()->count()) {
            return response()->json(['error' => 'Некоторые услуги недоступны'], 422);
        }

        if ($idol->isActiveBanned()) {
            return response()->json(['error' => 'Пользователь недоступен'], 422);
        }

        if (ChatBlock::active()->where('blocker_id', $idol->id)->where('blocked_id', $user->id)->exists()) {
            return response()->json(['error' => 'Вы заблокированы этим пользователем'], 422);
        }

        $order = Order::create([
            'customer_id' => $user->id,
            'idol_id'     => $idol->id,
            'status'      => OrderStatus::Pending,
        ]);
        $order->logStatusChange(null, OrderStatus::Pending->value, 'user', $user->id);

        foreach ($services as $service) {
            $qty = (int) ($quantityMap[$service->id]['quantity'] ?? 1);
            $order->items()->create(['service_id' => $service->id, 'quantity' => $qty]);
        }

        $conversation = Conversation::create(['order_id' => $order->id]);
        $conversation->participants()->createMany([
            ['user_id' => $user->id],
            ['user_id' => $idol->id],
        ]);

        $order->update(['conversation_id' => $conversation->id]);

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

        $order->load(['customer', 'idol', 'cancelledBy', 'items.service.timeUnit']);
        $this->safeBroadcast(new OrderChanged($idol->id,  $this->service->formatOrder($order, $idol->id),  'created'));
        $this->safeBroadcast(new OrderChanged($user->id,  $this->service->formatOrder($order, $user->id),  'created'));

        $idol->notify(new OrderCreatedNotification($order));
        $this->safeBroadcast(new NewNotification('private', $idol->id));

        return response()->json([
            'order_id'        => $order->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    public function accept(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->idol_id === $request->user()->id, 403);
        abort_unless($order->status === OrderStatus::Pending, 422);

        $this->service->accept($order, $request->user());

        return response()->json(['status' => 'accepted']);
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 403);
        abort_unless($order->status === OrderStatus::Accepted, 422);

        $this->service->pay($order, $request->user());

        return response()->json(['status' => 'paid']);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $order->customer_id === $user->id || $order->idol_id === $user->id,
            403
        );
        abort_unless(in_array($order->status, [OrderStatus::Pending, OrderStatus::Accepted]), 422);

        $request->validate(['cancel_reason' => 'required|string|max:1000']);

        $this->service->cancel($order, $user, $request->cancel_reason);

        return response()->json(['status' => 'cancelled']);
    }

    public function confirmCompletion(Request $request, Order $order): JsonResponse
    {
        abort_unless(
            $order->customer_id === $request->user()->id || $order->idol_id === $request->user()->id,
            403
        );
        abort_unless($order->status === OrderStatus::Paid, 422);

        $result = $this->service->confirmCompletion($order, $request->user());

        return response()->json($result);
    }

    public function disputable(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::where('customer_id', $user->id)
            ->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', now()->subHour())
            ->whereDoesntHave('disputes')
            ->with(['idol', 'items.service'])
            ->latest('completed_at')
            ->get()
            ->map(fn(Order $o) => [
                'id'           => $o->id,
                'idol_name'    => $o->idol->name,
                'total'        => $o->items->sum(fn($i) => ($i->service?->price ?? 0) * ($i->quantity ?? 1)),
                'created_at'   => $o->created_at->toISOString(),
                'completed_at' => $o->completed_at->toISOString(),
            ]);

        return response()->json($orders);
    }

    public function dispute(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($order->customer_id === $user->id, 403);
        abort_unless($order->status === OrderStatus::Completed, 422, 'Оспорить можно только выполненный заказ.');
        abort_unless($order->completed_at && $order->completed_at->gte(now()->subHour()), 422, 'Время для оспаривания истекло. Спор можно открыть в течение 1 часа после завершения заказа.');
        abort_unless(!$order->disputes()->exists(), 422, 'По этому заказу уже открыт спор.');

        $request->validate([
            'reason'  => ['required', Rule::in(self::DISPUTE_REASONS)],
            'details' => ['required', 'string', 'min:100'],
        ]);

        $details = trim($request->details);
        abort_unless(mb_strlen($details) >= 100, 422);

        $this->service->dispute($order, $user, $request->reason, $details);

        return response()->json(['success' => true]);
    }

    public function addItem(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($order->customer_id === $user->id, 403);
        abort_unless($order->status === OrderStatus::Pending, 422);

        $request->validate(['service_id' => ['required', 'integer', 'exists:services,id']]);

        $service = Service::where('id', $request->service_id)
            ->where('user_id', $order->idol_id)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->with('timeUnit:id,name')
            ->firstOrFail();

        $existing = $order->items()->where('service_id', $service->id)->first();
        if ($existing) {
            $existing->increment('quantity');
        } else {
            $order->items()->create(['service_id' => $service->id, 'quantity' => 1]);
        }

        $order->touch();

        if ($order->conversation_id) {
            $conv = $order->conversation;

            $order->load(['items.service.timeUnit']);
            $allItems = $order->items->map(fn($item) => [
                'id'        => $item->service?->id,
                'name'      => $item->service?->name,
                'price'     => $item->service?->price,
                'time_unit' => $item->service?->timeUnit?->name,
                'quantity'  => $item->quantity ?? 1,
            ])->values()->all();

            $msg  = $conv->messages()->create([
                'sender_id' => null,
                'body'      => '',
                'type'      => 'system',
                'metadata'  => [
                    'event'    => 'item_added',
                    'services' => $allItems,
                ],
            ]);
            $msg->load('sender');
            try {
                broadcast(new \App\Events\MessageSent($msg));
            } catch (\Throwable $e) {
                \Log::warning('Broadcast failed: ' . $e->getMessage());
            }
        }

        $order->load(['customer', 'idol', 'cancelledBy', 'items.service.timeUnit']);
        $this->safeBroadcast(new OrderChanged($order->idol_id,     $this->service->formatOrder($order, $order->idol_id),     'updated'));
        $this->safeBroadcast(new OrderChanged($order->customer_id, $this->service->formatOrder($order, $order->customer_id), 'updated'));

        return response()->json(['success' => true]);
    }

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $user    = $request->user();
        $perPage = 10;
        $cursor  = (int) $request->input('cursor', 0);
        $status  = $request->input('status');          // конкретный статус или null = все
        $role    = $request->input('role');             // 'customer' | 'idol' | null

        // Базовый скоуп по роли
        $scopeByRole = function ($q) use ($user, $role) {
            if ($role === 'customer') {
                $q->where('customer_id', $user->id);
            } elseif ($role === 'idol') {
                $q->where('idol_id', $user->id);
            } else {
                $q->where(fn($q) => $q->where('customer_id', $user->id)
                                      ->orWhere('idol_id', $user->id));
            }
        };

        // Реальное кол-во по каждому статусу из БД (без курсора и фильтра статуса)
        $rawCounts = Order::where($scopeByRole)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $allStatuses = ['pending', 'accepted', 'paid', 'completed', 'cancelled', 'refunded', 'disputed'];
        $counts = ['all' => 0];
        foreach ($allStatuses as $s) {
            $counts[$s]    = (int) ($rawCounts[$s] ?? 0);
            $counts['all'] += $counts[$s];
        }

        // Пагинированный список
        $search = trim($request->input('search', ''));

        $query = Order::where($scopeByRole)
            ->with([
                'customer',
                'idol',
                'cancelledBy',
                'items.service.category',
                'items.service.timeUnit',
                'conversation' => fn($q) => $q->with([
                    'participants' => fn($q) => $q->where('user_id', $user->id),
                ]),
            ])
            ->orderByDesc('id');

        if ($status) {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like, $role, $user) {
                if ($role === 'customer' || !$role) {
                    $q->orWhereHas('idol', fn($r) => $r->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]));
                }
                if ($role === 'idol' || !$role) {
                    $q->orWhereHas('customer', fn($r) => $r->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]));
                }
            });
        }
        if ($cursor > 0) {
            $query->where('id', '<', $cursor);
        }

        $items   = $query->limit($perPage + 1)->get();
        $hasMore = $items->count() > $perPage;
        if ($hasMore) {
            $items = $items->take($perPage);
        }

        $orders = $items->map(function (Order $order) use ($user) {
            $formatted                 = $this->service->formatOrder($order, $user->id);
            $formatted['unread_count'] = $this->getOrderUnreadCount($order, $user->id);
            return $formatted;
        });

        if ($request->wantsJson()) {
            return response()->json(['orders' => $orders, 'has_more' => $hasMore, 'counts' => $counts]);
        }

        return Inertia::render('Orders/Index', ['orders' => $orders]);
    }

    private function getOrderUnreadCount(Order $order, int $userId): int
    {
        if (! $order->conversation) return 0;
        $participant = $order->conversation->participants->firstWhere('user_id', $userId);
        return $order->conversation->messages()
            ->where(function ($q) use ($userId) {
                $q->whereNull('sender_id')->orWhere('sender_id', '!=', $userId);
            })
            ->when($participant?->last_read_at, fn($q, $date) => $q->where('created_at', '>', $date))
            ->count();
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
