<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Num;
use App\Support\WidgetFilters;

class GetKpisTool extends BaseMetricTool
{
    public function __construct(private readonly RollupQuery $rollups) {}

    public function name(): string
    {
        return 'get_kpis';
    }

    public function description(): string
    {
        return 'Headline numbers for a period: gross sales, discounts, cancellations, invoiced sales, returns, RTO, net sales, COGS, fees, logistics, contribution margin, orders, AOV, new vs repeat customers. Also returns the same figures for the previous period of equal length so you can state a change. Start here for any "how are we doing" question.';
    }

    public function permission(): string
    {
        return 'dashboard.kpi_strip.view';
    }

    /** @param array<string, mixed> $input */
    public function run(array $input, WidgetFilters $filters): array
    {
        $resolved = $this->resolve($input, $filters);
        $now = $this->rollups->totals($resolved);
        $prev = $this->rollups->totals($resolved->previous());

        return [
            'period' => $this->describePeriod($resolved),
            'channel' => $resolved->channelScope,
            'current' => $this->shape($now),
            'previous_period' => $this->shape($prev),
            'changes' => [
                'net_sales_pct' => $this->change($now['net_sales'], $prev['net_sales']),
                'orders_pct' => $this->change($now['orders_count'], $prev['orders_count']),
                'margin_pct_points' => round(
                    Num::pct($now['contribution_margin'], $now['net_sales']) - Num::pct($prev['contribution_margin'], $prev['net_sales']),
                    2,
                ),
            ],
        ];
    }

    /**
     * @param  array<string, int>  $totals
     * @return array<string, mixed>
     */
    private function shape(array $totals): array
    {
        return [
            'gross_sales' => $this->money($totals['gross_sales']),
            'discounts' => $this->money($totals['discounts']),
            'cancelled' => $this->money($totals['cancelled_amount']),
            'invoiced_sales' => $this->money($totals['invoiced_sales']),
            'customer_returns' => $this->money($totals['returned_amount']),
            'rto' => $this->money($totals['rto_amount']),
            'net_sales' => $this->money($totals['net_sales']),
            'cogs' => $this->money($totals['cogs']),
            'marketplace_fees' => $this->money($totals['marketplace_fees']),
            'logistics' => $this->money($totals['logistics_cost']),
            'gateway_fees' => $this->money($totals['gateway_fees']),
            'contribution_margin' => $this->money($totals['contribution_margin']),
            'contribution_margin_pct' => Num::pct($totals['contribution_margin'], $totals['net_sales']),
            'orders' => $totals['orders_count'],
            'aov' => $this->money((int) round(Num::safeDivide($totals['net_sales'], $totals['orders_count']))),
            'new_customers' => $totals['new_customers'],
            'repeat_customers' => $totals['repeat_customers'],
            'loss_making_orders' => $totals['loss_orders'],
        ];
    }

    private function change(int $now, int $prev): ?float
    {
        return $prev === 0 ? null : round((($now - $prev) / abs($prev)) * 100, 2);
    }
}
