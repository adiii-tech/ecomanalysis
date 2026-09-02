<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Domain\Rollups\Queries\MetricBuilder;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Facades\Tenant;
use App\Support\Metric;
use App\Support\Num;
use App\Support\Period;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * The KPI strips. Every card carries its own previous-period comparison and a
 * sparkline, and colours by goodness rather than direction.
 */
class KpiQuery
{
    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly MetricBuilder $metrics,
    ) {}

    /** @return list<array<string, mixed>> */
    public function dashboard(WidgetFilters $filters): array
    {
        ['current' => $now, 'previous' => $prev, 'series' => $series] = $this->metrics->context($filters);

        return [
            $this->metrics->make('gross_sales', 'Gross Sales', $now, $prev, $series, 'gross_sales', 'currency', true,
                'Everything customers agreed to pay, before discounts, cancellations and returns.', 'orders')->toArray(),

            $this->metrics->make('invoiced_sales', 'Invoiced Sales', $now, $prev, $series, 'invoiced_sales', 'currency', true,
                'Gross minus discounts and cancelled orders — what you actually billed.', 'orders')->toArray(),

            $this->metrics->make('net_sales', 'Net Sales', $now, $prev, $series, 'net_sales', 'currency', true,
                'Invoiced minus customer returns and RTO. This is the revenue you keep.', 'orders')->toArray(),

            $this->metrics->derived('orders', 'Orders',
                (float) $now['orders_count'], (float) $prev['orders_count'], $series,
                static fn (array $row): float => (float) $row['orders_count'],
                'number', true, 'Orders placed in the period. The badge shows how many were fulfilled.', 'orders')
                ->toArray() + ['badge' => $now['delivered_count'].' delivered'],

            $this->metrics->derived('contribution_margin_pct', 'Contribution Margin %',
                $this->metrics->marginPct($now), $this->metrics->marginPct($prev), $series,
                static fn (array $row): float => Num::pct($row['contribution_margin'], $row['net_sales']),
                'percent', true,
                'Net sales minus COGS, fees, logistics, packaging and gateway charges, as a share of net sales.',
                'order-profitability')->toArray(),

            $this->metrics->derived('aov', 'AOV',
                $this->metrics->aov($now), $this->metrics->aov($prev), $series,
                fn (array $row): float => (float) round(Num::safeDivide($row['net_sales'], $row['orders_count'])),
                'currency', true, 'Net sales divided by orders.', 'orders')->toArray(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function finance(WidgetFilters $filters): array
    {
        ['current' => $now, 'previous' => $prev, 'series' => $series] = $this->metrics->context($filters);
        $ads = $this->rollups->adSpend($filters);
        $adsPrev = $this->rollups->adSpend($filters->previous());

        $netProfit = $now['contribution_margin'] - $ads['total'];
        $netProfitPrev = $prev['contribution_margin'] - $adsPrev['total'];

        return [
            $this->metrics->make('gross_sales', 'Gross Sales', $now, $prev, $series, 'gross_sales')->toArray(),

            $this->metrics->derived('returning_customer_rate', 'Returning Customer Rate',
                $this->metrics->repeatRate($now), $this->metrics->repeatRate($prev), $series,
                static fn (array $row): float => Num::pct($row['repeat_customers'], $row['customers_count']),
                'percent', true, 'Share of buying customers in the period who had ordered before.')->toArray(),

            $this->metrics->make('orders', 'Orders', $now, $prev, $series, 'orders_count', 'number')->toArray(),

            (new Metric('ad_spend', 'Ads Spend', (float) $ads['total'], (float) $adsPrev['total'],
                'currency', false, 'Total spend across every connected ad platform.'))->toArray(),

            (new Metric('attributed_roas', 'Attributed ROAS',
                Num::ratio($ads['conversion_value'], $ads['total']),
                Num::ratio($adsPrev['conversion_value'], $adsPrev['total']),
                'ratio', true, 'Platform-reported conversion value divided by spend.'))->toArray(),

            (new Metric('net_profit', 'Net Profit', (float) $netProfit, (float) $netProfitPrev,
                'currency', true, 'Contribution margin minus ad spend. Fixed opex is excluded here — see the P&L for EBITDA.',
                [], 'Excludes fixed monthly opex; the P&L statement adds it to reach EBITDA.', 'pnl'))->toArray(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function marketplace(WidgetFilters $filters): array
    {
        ['current' => $now, 'previous' => $prev, 'series' => $series] = $this->metrics->context($filters);

        return [
            $this->metrics->make('invoiced_sales', 'Invoiced Sales', $now, $prev, $series, 'invoiced_sales')->toArray(),
            $this->metrics->make('net_sales', 'Net Sales', $now, $prev, $series, 'net_sales')->toArray(),
            $this->metrics->make('orders', 'Total Orders', $now, $prev, $series, 'orders_count', 'number')->toArray(),
            $this->metrics->derived('aov', 'AOV', $this->metrics->aov($now), $this->metrics->aov($prev), $series,
                static fn (array $row): float => (float) round(Num::safeDivide($row['net_sales'], $row['orders_count'])),
                'currency')->toArray(),

            $this->metrics->derived('cancelled', 'Cancelled',
                (float) $now['cancelled_orders'], (float) $prev['cancelled_orders'], $series,
                static fn (array $row): float => (float) $row['cancelled_orders'],
                'number', false, 'Cancelled orders in the period.')->toArray()
                + ['badge' => Num::pct($now['cancelled_orders'], $now['orders_count']).'% of cohort'],

            $this->metrics->derived('returns', 'Returns',
                (float) $now['returned_orders'], (float) $prev['returned_orders'], $series,
                static fn (array $row): float => (float) $row['returned_orders'],
                'number', false)->toArray()
                + ['badge' => $this->metrics->returnRate($now).'% of invoiced'],

            $this->metrics->derived('rto', 'RTO (cohort)',
                (float) $now['rto_orders'], (float) $prev['rto_orders'], $series,
                static fn (array $row): float => (float) $row['rto_orders'],
                'number', false, 'RTO events attributed to the order date, not the RTO date.')->toArray()
                + ['badge' => $this->metrics->rtoRate($now).'%'],
        ];
    }

    /**
     * The marketplace snapshot strip deliberately ignores the global date
     * filter — it is a "right now" view compared with yesterday.
     *
     * @return array<string, mixed>
     */
    public function todaySnapshot(WidgetFilters $filters, string $timezone): array
    {
        $today = Period::fromPreset('today', $timezone);
        $yesterday = Period::fromPreset('yesterday', $timezone);

        $todayTotals = $this->rollups->totals($filters->withPeriod($today));
        $yesterdayTotals = $this->rollups->totals($filters->withPeriod($yesterday));

        $inventory = DB::table('inventory as i')
            ->join('skus as s', 's.id', '=', 'i.sku_id')
            ->where('i.tenant_id', Tenant::id())
            ->selectRaw('COUNT(DISTINCT s.id) AS sku_count')
            ->selectRaw('SUM(CASE WHEN i.available <= 0 THEN 1 ELSE 0 END) AS out_of_stock')
            ->selectRaw('COALESCE(SUM(GREATEST(i.available, 0) * s.cost_price), 0) AS inventory_value')
            ->first();

        $skuCount = (int) ($inventory->sku_count ?? 0);

        return [
            'todays_sales' => ['value' => $todayTotals['invoiced_sales'], 'prev_value' => $yesterdayTotals['invoiced_sales'], 'format' => 'currency', 'label' => "Today's Sales (invoiced)"],
            'todays_order_items' => ['value' => $todayTotals['items_count'], 'prev_value' => $yesterdayTotals['items_count'], 'format' => 'number', 'label' => "Today's Order Items"],
            'sku_count' => ['value' => $skuCount, 'prev_value' => null, 'format' => 'number', 'label' => 'Total SKU Count'],
            'out_of_stock_pct' => ['value' => Num::pct((int) ($inventory->out_of_stock ?? 0), $skuCount), 'prev_value' => null, 'format' => 'percent', 'label' => 'Out-of-Stock %'],
            'inventory_value' => ['value' => (int) ($inventory->inventory_value ?? 0), 'prev_value' => null, 'format' => 'currency', 'label' => 'Inventory Value at Cost'],
        ];
    }
}
