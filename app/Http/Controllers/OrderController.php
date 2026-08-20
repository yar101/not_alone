<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\NewNotification;
use App\Events\OrderChanged;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use App\Services\OrderService;
use App\Traits\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrderController extends Controller
{
    use SafeBroadcast;

    public const DISPUTE_REASONS = [
        'Непристойное поведение',
        'Оскорбления',
        'Мошенничество',
        'Заказ не выполнен',
        'Угрозы',
        'Спам и навязывание',
    ];

    public function __construct(private OrderService $service) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $idol = User::findOrFail($request->integer('idol_id'));

        try {
            [$order, $conversation] = $this->service->createOrder($user, $idol, $request->services);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $this->safeBroadcast(new OrderChanged($idol->id, $this->service->formatOrder($order, $idol->id), 'created'));
        $this->safeBroadcast(new OrderChanged($user->id, $this->service->formatOrder($order, $user->id), 'created'));

        $idol->notify(new OrderCreatedNotification($order));
        $this->safeBroadcast(new NewNotification('private', $idol->id));

        return response()->json([
            'order_id' => $order->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    public function accept(Request $request, Order $order): JsonResponse
    {
        $this->authorize('accept', $order);

        try {
            $this->service->accept($order, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'accepted']);
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        $this->authorize('pay', $order);

        try {
            $this->service->pay($order, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'paid']);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $request->validate(['cancel_reason' => 'required|string|max:1000']);

        try {
            $this->service->cancel($order, $request->user(), $request->cancel_reason);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'cancelled']);
    }

    public function confirmCompletion(Request $request, Order $order): JsonResponse
    {
        $this->authorize('confirmCompletion', $order);

        try {
            $result = $this->service->confirmCompletion($order, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

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
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'idol_name' => $o->idol->name,
                'total' => $o->items->sum(fn ($i) => ($i->service?->price ?? 0) * ($i->quantity ?? 1)),
                'created_at' => $o->created_at->toISOString(),
                'completed_at' => $o->completed_at->toISOString(),
            ]);

        return response()->json($orders);
    }

    public function dispute(Request $request, Order $order): JsonResponse
    {
        $this->authorize('dispute', $order);

        $request->validate([
            'reason' => ['required', Rule::in(self::DISPUTE_REASONS)],
            'details' => ['required', 'string', 'min:100'],
        ]);

        $details = trim($request->details);
        abort_unless(mb_strlen($details) >= 100, 422);

        try {
            $this->service->dispute($order, $request->user(), $request->reason, $details);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true]);
    }

    public function addItem(Request $request, Order $order): JsonResponse
    {
        $this->authorize('addItem', $order);

        $request->validate(['service_id' => ['required', 'integer', 'exists:services,id']]);

        try {
            $this->service->addItem($order, $request->integer('service_id'), $request->user());
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true]);
    }

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $user = $request->user();
        $perPage = 10;
        $cursor = (int) $request->input('cursor', 0);
        $status = $request->input('status');
        $role = $request->input('role');

        $scopeByRole = function ($q) use ($user, $role) {
            if ($role === 'customer') {
                $q->where('customer_id', $user->id);
            } elseif ($role === 'idol') {
                $q->where('idol_id', $user->id);
            } else {
                $q->where(fn ($q) => $q->where('customer_id', $user->id)
                    ->orWhere('idol_id', $user->id));
            }
        };

        $rawCounts = Order::where($scopeByRole)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $allStatuses = ['pending', 'accepted', 'paid', 'completed', 'cancelled', 'refunded', 'disputed'];
        $counts = ['all' => 0];
        foreach ($allStatuses as $s) {
            $counts[$s] = (int) ($rawCounts[$s] ?? 0);
            $counts['all'] += $counts[$s];
        }

        $search = trim($request->input('search', ''));

        $query = Order::where($scopeByRole)
            ->with([
                'customer',
                'idol',
                'cancelledBy',
                'items.service.category',
                'items.service.timeUnit',
                'conversation' => fn ($q) => $q->with([
                    'participants' => fn ($q) => $q->where('user_id', $user->id),
                ]),
            ])
            ->orderByDesc('id');

        if ($status) {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like, $role) {
                if ($role === 'customer' || ! $role) {
                    $q->orWhereHas('idol', fn ($r) => $r->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]));
                }
                if ($role === 'idol' || ! $role) {
                    $q->orWhereHas('customer', fn ($r) => $r->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]));
                }
            });
        }

        if ($request->boolean('unread')) {
            $userId = $user->id;
            $query->whereHas('conversation.participants', function ($pq) use ($userId) {
                $pq->where('user_id', $userId)->where('has_unread', true);
            });
        }

        if ($cursor > 0) {
            $query->where('id', '<', $cursor);
        }

        $items = $query->limit($perPage + 1)->get();
        $hasMore = $items->count() > $perPage;
        if ($hasMore) {
            $items = $items->take($perPage);
        }

        $orders = $items->map(function (Order $order) use ($user) {
            $formatted = $this->service->formatOrder($order, $user->id);
            $formatted['unread'] = $this->getOrderUnread($order, $user->id);

            return $formatted;
        });

        if ($request->wantsJson()) {
            return response()->json(['orders' => $orders, 'has_more' => $hasMore, 'counts' => $counts]);
        }

        return Inertia::render('Orders/Index', ['orders' => $orders]);
    }

    private function getOrderUnread(Order $order, int $userId): bool
    {
        if (! $order->conversation) {
            return false;
        }
        $participant = $order->conversation->participants->firstWhere('user_id', $userId);

        return (bool) ($participant->has_unread ?? false);
    }
}
