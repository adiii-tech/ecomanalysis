<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Enums\PaymentMode;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * COD vs prepaid economics — the single most India-specific number in the
 * product. COD looks like revenue until you subtract RTO, and this widget is
 * where that becomes obvious.
 */
class PaymentModeQuery
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /** @return array<string, mixed> */
    public function handle(WidgetFilters $filters): array
    {
        $rows = $this->rollups->byPaymentMode($filters);
        $totalNet = (int) $rows->sum('net_sales');
        $totalOrders = (int) $rows->sum('orders_count');

        $modes = collect(PaymentMode::cases())->map(function (PaymentMode $mode) use ($rows, $totalNet, $totalOrders): array {
            $row = $rows->firstWhere('dimension', $mode->value);
            $netSales = (int) ($row->net_sales ?? 0);
            $margin = (int) ($row->contribution_margin ?? 0);
            $orders = (int) ($row->orders_count ?? 0);
            $invoiced = (int) ($row->invoiced_orders ?? 0);

            return [
                'mode' => $mode->value,
                'label' => $mode->label(),
                'orders' => $orders,
                'net_sales' => $netSales,
                'cogs' => (int) ($row->cogs ?? 0),
                'fees' => (int) ($row->marketplace_fees ?? 0) + (int) ($row->gateway_fees ?? 0),
                'logistics' => (int) ($row->logistics_cost ?? 0),
                'net_margin' => $margin,
                'net_margin_pct' => Num::pct($margin, $netSales),
                'share_of_sales' => Num::pct($netSales, $totalNet),
                'share_of_orders' => Num::pct($orders, $totalOrders),
                'rto_orders' => (int) ($row->rto_orders ?? 0),
                'rto_pct' => Num::pct((int) ($row->rto_orders ?? 0), $invoiced),
                'return_pct' => Num::pct((int) ($row->returned_orders ?? 0), $invoiced),
                'aov' => (int) round(Num::safeDivide($netSales, $orders)),
            ];
        })->all();

        return [
            'rows' => $modes,
            'verdict' => $this->verdict($modes)->toArray(),
        ];
    }

    /** @param list<array<string, mixed>> $modes */
    private function verdict(array $modes): Verdict
    {
        $cod = collect($modes)->firstWhere('mode', 'cod');
        $prepaid = collect($modes)->firstWhere('mode', 'prepaid');

        if ($cod === null || $prepaid === null || ($cod['orders'] === 0 && $prepaid['orders'] === 0)) {
            return Verdict::neutral('No orders in this window.');
        }

        if ($cod['orders'] === 0) {
            return Verdict::good('Every order in this window was prepaid.', 'No COD exposure and no RTO risk from cash orders.');
        }

        if ($prepaid['orders'] === 0) {
            return Verdict::watch('Every order was COD.', sprintf('RTO on COD is running at %.1f%%.', $cod['rto_pct']),
                'Offer a small prepaid discount — it usually pays for itself in avoided RTO.');
        }

        $gap = round($prepaid['net_margin_pct'] - $cod['net_margin_pct'], 1);

        if ($gap > 0.5) {
            $shiftableOrders = (int) round($cod['orders'] * 0.2);
            $impact = (int) round($shiftableOrders * Num::safeDivide($cod['net_sales'], max($cod['orders'], 1)) * $gap / 100);

            return new Verdict(
                Verdict::WATCH,
                sprintf('Prepaid is %.1f percentage points more profitable than COD.', $gap),
                sprintf('COD margin %.1f%% vs prepaid %.1f%%, driven mainly by %.1f%% RTO on COD.', $cod['net_margin_pct'], $prepaid['net_margin_pct'], $cod['rto_pct']),
                sprintf('Shifting just 20%% of COD orders to prepaid is worth about %s in this window.', Money::compact($impact)),
                $impact,
            );
        }

        if ($gap < -0.5) {
            return Verdict::good(
                sprintf('COD is currently %.1f points more profitable than prepaid.', abs($gap)),
                'Unusual — check that gateway fees on prepaid are configured correctly in cost settings.',
            );
        }

        return Verdict::neutral('COD and prepaid are earning roughly the same margin.',
            sprintf('Within half a point of each other (%.1f%% vs %.1f%%).', $cod['net_margin_pct'], $prepaid['net_margin_pct']));
    }
}
