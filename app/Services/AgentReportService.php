<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\AgentReport;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AgentReportService
{
    /**
     * Generate monthly agent report for an idol for a specific month.
     * Pursuant to Art. 1008 of the Civil Code of the Russian Federation (ст. 1008 ГК РФ).
     */
    public function generateMonthlyReport(User $idol, Carbon $month): ?AgentReport
    {
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        // Idempotency: check if report already exists for this idol and month
        $existing = AgentReport::where('idol_id', $idol->id)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        // Fetch all completed/closed orders for this idol in the specified period
        $orders = Order::where('idol_id', $idol->id)
            ->whereIn('status', [OrderStatus::Completed, OrderStatus::Closed])
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->with(['items.service', 'customer'])
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $reportNumber = sprintf('АР-%s-%04d', $periodStart->format('Ym'), $idol->id);

        $grossSales = 0.00;
        $platformFeeAmount = 0.00;
        $customerServiceFeeAmount = 0.00;
        $netPayoutAmount = 0.00;
        $breakdown = [];

        foreach ($orders as $order) {
            $orderTotal = (float) $order->total_price;
            $platformFee = (float) bcdiv((string) bcmul((string) $orderTotal, '12.0', 4), '100', 2);
            $custFee = (float) ($order->customer_service_fee ?? bcdiv((string) bcmul((string) $orderTotal, '5.0', 4), '100', 2));
            $net = (float) bcsub((string) $orderTotal, (string) $platformFee, 2);

            $grossSales = (float) bcadd((string) $grossSales, (string) $orderTotal, 2);
            $platformFeeAmount = (float) bcadd((string) $platformFeeAmount, (string) $platformFee, 2);
            $customerServiceFeeAmount = (float) bcadd((string) $customerServiceFeeAmount, (string) $custFee, 2);
            $netPayoutAmount = (float) bcadd((string) $netPayoutAmount, (string) $net, 2);

            $serviceNames = $order->items->map(fn ($i) => $i->service?->name ?? 'Услуга')->join(', ');

            $breakdown[] = [
                'order_id' => $order->id,
                'created_at' => $order->created_at->toISOString(),
                'completed_at' => $order->completed_at?->toISOString() ?? $order->created_at->toISOString(),
                'customer_id' => $order->customer_id,
                'services' => $serviceNames,
                'gross_amount' => $orderTotal,
                'platform_fee' => $platformFee,
                'customer_fee' => $custFee,
                'net_payout' => $net,
                'status' => $order->status->value,
            ];
        }

        // Acceptance deadline: 10 business days (~14 calendar days)
        $deadline = now()->addDays(14);

        return AgentReport::create([
            'idol_id' => $idol->id,
            'report_number' => $reportNumber,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'orders_count' => $orders->count(),
            'gross_sales' => $grossSales,
            'platform_fee_amount' => $platformFeeAmount,
            'customer_service_fee_amount' => $customerServiceFeeAmount,
            'net_payout_amount' => $netPayoutAmount,
            'status' => 'pending_acceptance',
            'acceptance_deadline' => $deadline,
            'items_breakdown' => $breakdown,
        ]);
    }

    /**
     * Generate reports for all eligible idols for the given month.
     */
    public function generateAllReportsForMonth(Carbon $month): Collection
    {
        $idols = User::where('is_idol', true)->get();
        $generated = collect();

        foreach ($idols as $idol) {
            try {
                $report = $this->generateMonthlyReport($idol, $month);
                if ($report) {
                    $generated->push($report);
                }
            } catch (\Throwable $e) {
                Log::error("Failed to generate agent report for idol #{$idol->id}: ".$e->getMessage());
            }
        }

        return $generated;
    }

    /**
     * Auto-accept agent reports whose acceptance deadline has passed.
     */
    public function autoAcceptExpiredReports(): int
    {
        return AgentReport::where('status', 'pending_acceptance')
            ->where('acceptance_deadline', '<=', now())
            ->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
    }
}
