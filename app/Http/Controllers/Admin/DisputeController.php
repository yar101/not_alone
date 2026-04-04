<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderDispute;
use App\Services\AdminLogService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): Response
    {
        $statusFilter = $request->get('status', 'open');

        $query = OrderDispute::with([
            'order.customer:id,name,avatar_path',
            'order.idol:id,name,avatar_path',
            'order.items.service',
            'customer:id,name,avatar_path',
        ])->latest('created_at');

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $disputes = $query->paginate(20)->through(fn(OrderDispute $d) => $this->format($d));

        return Inertia::render('Admin/Disputes/Index', [
            'disputes'      => $disputes,
            'status_filter' => $statusFilter,
            'counts'        => [
                'open'     => OrderDispute::where('status', 'open')->count(),
                'approved' => OrderDispute::where('status', 'approved')->count(),
                'rejected' => OrderDispute::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function resolve(Request $request, OrderDispute $dispute): RedirectResponse
    {
        $request->validate([
            'decision'   => ['required', 'in:approved,rejected'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($dispute->status !== 'open') {
            return back()->withErrors(['dispute' => 'Спор уже рассмотрен.']);
        }

        $adminId = auth('admin')->id();
        $order   = $dispute->order;

        $dispute->update([
            'status'      => $request->decision,
            'admin_note'  => $request->admin_note,
            'resolved_at' => now(),
        ]);

        // Apply order status change matching the decision
        $targetStatus = $request->decision === 'approved'
            ? OrderStatus::Refunded
            : OrderStatus::Completed;

        $note = $request->decision === 'approved'
            ? 'Спор удовлетворён: ' . ($request->admin_note ?? '')
            : 'Спор отклонён: '    . ($request->admin_note ?? '');

        $this->orderService->adminTransition($order, $targetStatus, $adminId, trim($note));

        AdminLogService::log(
            $adminId,
            'resolve_dispute',
            'order_dispute',
            $dispute->id,
            ['decision' => $request->decision, 'order_id' => $order->id, 'note' => $request->admin_note]
        );

        return back();
    }

    private function format(OrderDispute $d): array
    {
        return [
            'id'          => $d->id,
            'order_id'    => $d->order_id,
            'reason'      => $d->reason,
            'details'     => $d->details,
            'status'      => $d->status,
            'admin_note'  => $d->admin_note,
            'created_at'  => $d->created_at->format('d.m.Y H:i'),
            'resolved_at' => $d->resolved_at?->format('d.m.Y H:i'),
            'customer'    => [
                'id'     => $d->customer->id,
                'name'   => $d->customer->name,
                'avatar' => $d->customer->avatar_url,
            ],
            'order' => [
                'status'          => $d->order->status->value,
                'status_label'    => $d->order->status->label(),
                'total'           => $d->order->items->sum(fn($i) => ($i->service?->price ?? 0) * ($i->quantity ?? 1)),
                'created_at'      => $d->order->created_at->format('d.m.Y H:i'),
                'paid_at'         => $d->order->paid_at?->format('d.m.Y H:i'),
                'completed_at'    => $d->order->completed_at?->format('d.m.Y H:i'),
                'conversation_id' => $d->order->conversation_id,
                'customer'        => [
                    'id'     => $d->order->customer->id,
                    'name'   => $d->order->customer->name,
                    'avatar' => $d->order->customer->avatar_url,
                ],
                'idol' => [
                    'id'     => $d->order->idol->id,
                    'name'   => $d->order->idol->name,
                    'avatar' => $d->order->idol->avatar_url,
                ],
            ],
        ];
    }
}
