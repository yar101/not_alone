<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\ServiceCategory;
use App\Models\WalletTransaction;
use App\Services\AdminLogService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): Response
    {
        $query = Order::with([
            'customer:id,name,email',
            'idol:id,name,email',
            'items.service.category',
        ])->latest();

        if ($s = $request->search) {
            $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($s, $likeOp) {
                $q->where('id', is_numeric($s) ? (int) $s : 0)
                    ->orWhereHas('customer', fn ($q) => $q->where('name', $likeOp, "%{$s}%")->orWhere('email', $likeOp, "%{$s}%"))
                    ->orWhereHas('idol', fn ($q) => $q->where('name', $likeOp, "%{$s}%")->orWhere('email', $likeOp, "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('idol_id')) {
            $query->where('idol_id', $request->idol_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('category_id')) {
            $query->whereHas('items.service', fn ($q) => $q->where('category_id', $request->category_id));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $rawCounts = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $counts = [
            'total' => $rawCounts->sum(),
            'pending' => (int) ($rawCounts[OrderStatus::Pending->value] ?? 0),
            'accepted' => (int) ($rawCounts[OrderStatus::Accepted->value] ?? 0),
            'paid' => (int) ($rawCounts[OrderStatus::Paid->value] ?? 0),
            'completed' => (int) ($rawCounts[OrderStatus::Completed->value] ?? 0),
            'cancelled' => (int) ($rawCounts[OrderStatus::Cancelled->value] ?? 0),
            'refunded' => (int) ($rawCounts[OrderStatus::Refunded->value] ?? 0),
            'disputed' => (int) ($rawCounts[OrderStatus::Disputed->value] ?? 0),
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $query->paginate(30)->withQueryString()->through(fn (Order $o) => [
                'id' => $o->id,
                'customer' => ['id' => $o->customer_id, 'name' => $o->customer?->name ?? '—', 'email' => $o->customer?->email ?? '—'],
                'idol' => ['id' => $o->idol_id,     'name' => $o->idol?->name ?? '—',     'email' => $o->idol?->email ?? '—'],
                'categories' => $o->items->map(fn ($i) => $i->service?->category?->name)->filter()->unique()->values(),
                'items_count' => $o->items->count(),
                'total' => $o->items->sum(fn ($i) => ($i->price ?? $i->service?->price ?? 0) * ($i->quantity ?? 1)),
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'status_color' => $o->status->color(),
                'cancel_reason' => $o->cancel_reason,
                'created_at' => $o->created_at->format('d.m.Y H:i'),
            ]),
            'counts' => $counts,
            'filters' => $request->only(['search', 'status', 'idol_id', 'customer_id', 'category_id', 'date_from', 'date_to']),
            'categories' => ServiceCategory::orderBy('name')->get(['id', 'name']),
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load([
            'customer' => fn ($q) => $q->withTrashed(),
            'idol' => fn ($q) => $q->withTrashed(),
            'cancelledBy' => fn ($q) => $q->withTrashed(),
            'items.service.category',
            'items.service.timeUnit',
            'disputes' => fn ($q) => $q->latest('created_at'),
            'disputes.customer' => fn ($q) => $q->withTrashed(),
            'statusHistory' => fn ($q) => $q->latest('created_at'),
            'conversation',
        ]);

        $transactions = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->with('user')
            ->orderByDesc('id')
            ->get()
            ->map(fn (WalletTransaction $t) => [
                'id' => $t->id,
                'user' => [
                    'id' => $t->user?->id,
                    'name' => $t->user?->name ?? '—',
                    'email' => $t->user?->email,
                ],
                'type' => $t->type instanceof \BackedEnum ? $t->type->value : (string) $t->type,
                'amount' => (float) $t->amount,
                'balance_before' => (float) $t->balance_before,
                'balance_after' => (float) $t->balance_after,
                'status' => $t->status instanceof \BackedEnum ? $t->status->value : (string) $t->status,
                'description' => $t->description,
                'metadata' => $t->metadata,
                'created_at' => $t->created_at?->format('d.m.Y H:i:s'),
            ]);

        $items = $order->items->map(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title ?? $item->service?->name ?? ('Услуга #'.$item->service_id),
            'category_name' => $item->service?->category?->name ?? '—',
            'time_unit' => $item->service?->timeUnit?->name ?? '—',
            'price' => (float) ($item->price ?? $item->service?->price ?? 0),
            'quantity' => (int) ($item->quantity ?? 1),
            'subtotal' => (float) (($item->price ?? $item->service?->price ?? 0) * ($item->quantity ?? 1)),
            'options' => $item->options ?? [],
        ]);

        $totalAmount = $items->sum('subtotal');

        $statusHistory = $order->statusHistory->map(function ($h) {
            $fromEnum = $h->from_status ? OrderStatus::tryFrom($h->from_status) : null;
            $toEnum = OrderStatus::tryFrom($h->to_status);

            return [
                'id' => $h->id,
                'from' => $fromEnum ? $fromEnum->label() : $h->from_status,
                'to' => $toEnum ? $toEnum->label() : $h->to_status,
                'to_color' => $toEnum ? $toEnum->color() : 'gray',
                'actor_type' => $h->actor_type,
                'actor_id' => $h->actor_id,
                'note' => $h->note,
                'created_at' => $h->created_at->format('d.m.Y H:i'),
            ];
        });

        $disputes = $order->disputes->map(fn ($d) => [
            'id' => $d->id,
            'reason' => $d->reason,
            'details' => $d->details,
            'status' => $d->status,
            'admin_note' => $d->admin_note,
            'created_at' => $d->created_at?->format('d.m.Y H:i'),
            'resolved_at' => $d->resolved_at?->format('d.m.Y H:i'),
            'customer_name' => $d->customer?->name ?? '—',
        ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_color' => $order->status->color(),
                'cancel_reason' => $order->cancel_reason,
                'cancelled_by' => $order->cancelledBy ? [
                    'id' => $order->cancelledBy->id,
                    'name' => $order->cancelledBy->name,
                ] : null,
                'created_at' => $order->created_at->format('d.m.Y H:i'),
                'paid_at' => $order->paid_at?->format('d.m.Y H:i'),
                'completed_at' => $order->completed_at?->format('d.m.Y H:i'),
                'total' => $totalAmount,
                'conversation_id' => $order->conversation_id ?? Conversation::where('order_id', $order->id)->value('id'),
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'avatar_url' => $order->customer->avatar_url,
                    'is_banned' => (bool) $order->customer->is_banned,
                    'is_deleted' => (bool) $order->customer->trashed(),
                ] : null,
                'idol' => $order->idol ? [
                    'id' => $order->idol->id,
                    'name' => $order->idol->name,
                    'email' => $order->idol->email,
                    'avatar_url' => $order->idol->avatar_url,
                    'rating' => $order->idol->rating,
                    'is_banned' => (bool) $order->idol->is_banned,
                    'is_deleted' => (bool) $order->idol->trashed(),
                ] : null,
                'items' => $items,
                'transactions' => $transactions,
                'history' => $statusHistory,
                'disputes' => $disputes,
            ],
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $from = $order->status->value;
        $to = OrderStatus::from($request->status);
        $adminId = auth('admin')->id();

        try {
            $this->orderService->adminTransition($order, $to, $adminId, $request->admin_note);
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AdminLogService::log(
            $adminId,
            'change_order_status',
            'order',
            $order->id,
            ['from' => $from, 'to' => $request->status, 'note' => $request->admin_note]
        );

        return back()->with('success', 'Статус заказа обновлен.');
    }

    public function history(Order $order): JsonResponse
    {
        return response()->json(
            $order->statusHistory->map(fn ($h) => [
                'from' => $h->from_status ? OrderStatus::from($h->from_status)->label() : null,
                'to' => OrderStatus::from($h->to_status)->label(),
                'to_color' => OrderStatus::from($h->to_status)->color(),
                'actor_type' => $h->actor_type,
                'actor_id' => $h->actor_id,
                'note' => $h->note,
                'created_at' => $h->created_at->format('d.m.Y H:i'),
            ])
        );
    }
}
