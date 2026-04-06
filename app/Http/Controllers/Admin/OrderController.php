<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ServiceCategory;
use App\Services\AdminLogService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $query->where(function ($q) use ($s) {
                $q->where('id', is_numeric($s) ? (int) $s : 0)
                  ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
                  ->orWhereHas('idol', fn($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
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
            $query->whereHas('items.service', fn($q) => $q->where('category_id', $request->category_id));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $counts = [
            'total'    => Order::count(),
            'pending'  => Order::where('status', OrderStatus::Pending)->count(),
            'accepted' => Order::where('status', OrderStatus::Accepted)->count(),
            'paid'     => Order::where('status', OrderStatus::Paid)->count(),
            'completed'=> Order::where('status', OrderStatus::Completed)->count(),
            'cancelled'=> Order::where('status', OrderStatus::Cancelled)->count(),
            'refunded' => Order::where('status', OrderStatus::Refunded)->count(),
            'disputed' => Order::where('status', OrderStatus::Disputed)->count(),
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders'     => $query->paginate(30)->through(fn(Order $o) => [
                'id'            => $o->id,
                'customer'      => ['id' => $o->customer_id, 'name' => $o->customer->name, 'email' => $o->customer->email],
                'idol'          => ['id' => $o->idol_id,     'name' => $o->idol->name,     'email' => $o->idol->email],
                'categories'    => $o->items->map(fn($i) => $i->service?->category?->name)->filter()->unique()->values(),
                'items_count'   => $o->items->count(),
                'total'         => $o->items->sum(fn($i) => ($i->service?->price ?? 0) * ($i->quantity ?? 1)),
                'status'        => $o->status->value,
                'status_label'  => $o->status->label(),
                'status_color'  => $o->status->color(),
                'cancel_reason' => $o->cancel_reason,
                'created_at'    => $o->created_at->format('d.m.Y H:i'),
            ]),
            'counts'     => $counts,
            'filters'    => $request->only(['search', 'status', 'idol_id', 'customer_id', 'category_id', 'date_from', 'date_to']),
            'categories' => ServiceCategory::orderBy('name')->get(['id', 'name']),
            'statuses'   => collect(OrderStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'status'     => ['required', Rule::enum(OrderStatus::class)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $from     = $order->status->value;
        $to       = OrderStatus::from($request->status);
        $adminId  = auth('admin')->id();

        $this->orderService->adminTransition($order, $to, $adminId, $request->admin_note);

        AdminLogService::log(
            $adminId,
            'change_order_status',
            'order',
            $order->id,
            ['from' => $from, 'to' => $request->status, 'note' => $request->admin_note]
        );

        return back();
    }

    public function history(Order $order): JsonResponse
    {
        return response()->json(
            $order->statusHistory->map(fn($h) => [
                'from'       => $h->from_status ? OrderStatus::from($h->from_status)->label() : null,
                'to'         => OrderStatus::from($h->to_status)->label(),
                'to_color'   => OrderStatus::from($h->to_status)->color(),
                'actor_type' => $h->actor_type,
                'actor_id'   => $h->actor_id,
                'note'       => $h->note,
                'created_at' => $h->created_at->format('d.m.Y H:i'),
            ])
        );
    }
}
