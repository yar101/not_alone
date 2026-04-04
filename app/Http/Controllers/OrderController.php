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
        'Предоставлен некачественный результат',
        'Нарушение условий сервиса',
        'Угрозы',
        'Спам и навязывание',
        'Нарушение авторских прав',
        'Другое',
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
            ->with('idol')
            ->latest('completed_at')
            ->get()
            ->map(fn(Order $o) => [
                'id'         => $o->id,
                'idol_name'  => $o->idol->name,
                'created_at' => $o->created_at->toISOString(),
            ]);

        return response()->json($orders);
    }

    public function dispute(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($order->customer_id === $user->id, 403);
        abort_unless($order->status === OrderStatus::Completed, 422);
        abort_unless($order->completed_at && $order->completed_at->gte(now()->subHour()), 422);
        abort_unless(!$order->disputes()->exists(), 422);

        $request->validate([
            'reason'  => ['required', Rule::in(self::DISPUTE_REASONS)],
            'details' => ['required', 'string', 'min:35'],
        ]);

        $details = trim($request->details);
        abort_unless(mb_strlen($details) >= 35, 422);

        $this->service->dispute($order, $user, $request->reason, $details);

        return response()->json(['success' => true]);
    }

    public function index(Request $request): JsonResponse|InertiaResponse
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
            ->map(fn(Order $order) => $this->service->formatOrder($order, $user->id));

        if ($request->wantsJson()) {
            return response()->json(['orders' => $orders]);
        }

        return Inertia::render('Orders/Index', ['orders' => $orders]);
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
