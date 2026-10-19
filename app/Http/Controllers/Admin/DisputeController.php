<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderDispute;
use App\Services\AdminLogService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('order_id', (int) $search);
                }
                $q->orWhereHas('customer', fn ($cq) => $cq->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
                  ->orWhereHas('order.idol', fn ($iq) => $iq->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
                  ->orWhere('reason', 'ilike', "%{$search}%")
                  ->orWhere('details', 'ilike', "%{$search}%");
            });
        }

        $disputes = $query->paginate(20)->withQueryString()->through(fn (OrderDispute $d) => $this->format($d));

        $rawCounts = OrderDispute::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('Admin/Disputes/Index', [
            'disputes' => $disputes,
            'status_filter' => $statusFilter,
            'search' => $search,
            'counts' => [
                'open' => (int) ($rawCounts['open'] ?? 0),
                'approved' => (int) ($rawCounts['approved'] ?? 0),
                'rejected' => (int) ($rawCounts['rejected'] ?? 0),
            ],
        ]);
    }

    public function resolve(Request $request, OrderDispute $dispute): RedirectResponse
    {
        $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($dispute->status !== 'open') {
            return back()->withErrors(['dispute' => 'Спор уже рассмотрен.']);
        }

        $adminId = auth('admin')->id();

        try {
            DB::transaction(function () use ($request, $dispute, $adminId) {
                $lockedDispute = OrderDispute::where('id', $dispute->id)->lockForUpdate()->first();
                if (! $lockedDispute || $lockedDispute->status !== 'open') {
                    throw new \DomainException('Спор уже рассмотрен.');
                }

                $order = $lockedDispute->order;

                $lockedDispute->update([
                    'status' => $request->decision,
                    'admin_note' => $request->admin_note,
                    'resolved_at' => now(),
                ]);

                // Apply order status change matching the decision
                $targetStatus = $request->decision === 'approved'
                    ? OrderStatus::Refunded
                    : OrderStatus::Completed;

                $note = $request->decision === 'approved'
                    ? 'Спор удовлетворён: '.($request->admin_note ?? '')
                    : 'Спор отклонён: '.($request->admin_note ?? '');

                $this->orderService->adminTransition($order, $targetStatus, $adminId, trim($note));

                AdminLogService::log(
                    $adminId,
                    'resolve_dispute',
                    'order_dispute',
                    $lockedDispute->id,
                    ['decision' => $request->decision, 'order_id' => $order->id, 'note' => $request->admin_note]
                );
            });
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['dispute' => $e->getMessage()]);
        }

        return back();
    }

    private function format(OrderDispute $d): array
    {
        return [
            'id' => $d->id,
            'order_id' => $d->order_id,
            'reason' => $d->reason,
            'details' => $d->details,
            'status' => $d->status,
            'admin_note' => $d->admin_note,
            'created_at' => $d->created_at->format('d.m.Y H:i'),
            'resolved_at' => $d->resolved_at?->format('d.m.Y H:i'),
            'customer' => [
                'id' => $d->customer?->id,
                'name' => $d->customer?->name ?? 'Удаленный пользователь',
                'avatar' => $d->customer?->avatar_url,
                'is_deleted' => (bool) $d->customer?->trashed(),
            ],
            'order' => [
                'status' => $d->order?->status?->value ?? 'unknown',
                'status_label' => $d->order?->status?->label() ?? '—',
                'total' => $d->order ? $d->order->items->sum(fn ($i) => ($i->price ?? $i->service?->price ?? 0) * ($i->quantity ?? 1)) : 0,
                'created_at' => $d->order?->created_at?->format('d.m.Y H:i'),
                'paid_at' => $d->order?->paid_at?->format('d.m.Y H:i'),
                'completed_at' => $d->order?->completed_at?->format('d.m.Y H:i'),
                'conversation_id' => $d->order?->conversation_id,
                'customer' => [
                    'id' => $d->order?->customer?->id,
                    'name' => $d->order?->customer?->name ?? 'Удаленный пользователь',
                    'avatar' => $d->order?->customer?->avatar_url,
                ],
                'idol' => [
                    'id' => $d->order?->idol?->id,
                    'name' => $d->order?->idol?->name ?? 'Удаленный пользователь',
                    'avatar' => $d->order?->idol?->avatar_url,
                ],
            ],
        ];
    }
}
