<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * The gross → net waterfall, in both table and chart form. This is the spine of
 * the product: every other number is a slice of this chain.
 */
class SalesSummaryQuery
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /** @return array<string, mixed> */
    public function handle(WidgetFilters $filters): array
    {
        $t = $this->rollups->totals($filters);

        $rows = [
            $this->row('gross_sales', 'Gross Sales', $t['orders_count'], $t['gross_sales'], 'positive',
                'Everything customers agreed to pay, before any deductions.'),
            $this->row('discounts', 'Discounts', 0, -$t['discounts'], 'negative',
                'Coupon and automatic discounts applied at checkout.'),
            $this->row('cancelled', 'Cancelled', $t['cancelled_orders'], -$t['cancelled_amount'], 'negative',
                'Orders cancelled before invoicing.'),
            $this->row('invoiced_sales', 'Invoiced Sales', $t['invoiced_orders'], $t['invoiced_sales'], 'subtotal',
                'Gross minus discounts and cancellations — what you actually billed.'),
            $this->row('returned', 'Returned', $t['returned_orders'], -$t['returned_amount'], 'negative',
                'Customer returns refunded against invoiced orders.'),
            $this->row('rto', 'RTO', $t['rto_orders'], -$t['rto_amount'], 'negative',
                'Shipments returned to origin without ever being delivered.'),
            $this->row('net_sales', 'Net Sales', $t['invoiced_orders'] - $t['returned_orders'] - $t['rto_orders'], $t['net_sales'], 'total',
                'Revenue you actually keep before costs.'),
            $this->row('shipping', 'Shipping Collected', 0, $t['shipping_collected'], 'neutral',
                'Shipping charged to customers, already included in invoiced sales.'),
            $this->row('return_fees', 'Return & RTO Handling', $t['returned_orders'] + $t['rto_orders'], -$t['return_cost'], 'negative',
                'Reverse logistics and restocking cost for returns and RTO.'),
        ];

        $leakagePct = Num::pct($t['discounts'] + $t['cancelled_amount'] + $t['returned_amount'] + $t['rto_amount'], $t['gross_sales']);

        return [
            'rows' => $rows,
            'totals' => $t,
            'leakage_pct' => $leakagePct,
            'verdict' => $this->verdict($t, $leakagePct)->toArray(),
        ];
    }

    /**
     * The same chain as a waterfall chart series.
     *
     * @return list<array<string, mixed>>
     */
    public function waterfall(WidgetFilters $filters): array
    {
        $t = $this->rollups->totals($filters);
        $running = 0;
        $steps = [
            ['key' => 'gross_sales', 'label' => 'Gross', 'delta' => $t['gross_sales']],
            ['key' => 'discounts', 'label' => 'Discounts', 'delta' => -$t['discounts']],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'delta' => -$t['cancelled_amount']],
            ['key' => 'returned', 'label' => 'Returns', 'delta' => -$t['returned_amount']],
            ['key' => 'rto', 'label' => 'RTO', 'delta' => -$t['rto_amount']],
            ['key' => 'cogs', 'label' => 'COGS', 'delta' => -$t['cogs']],
            ['key' => 'fees', 'label' => 'Marketplace fees', 'delta' => -$t['marketplace_fees']],
            ['key' => 'logistics', 'label' => 'Logistics', 'delta' => -$t['logistics_cost']],
            ['key' => 'packaging', 'label' => 'Packaging', 'delta' => -$t['packaging_cost']],
            ['key' => 'gateway', 'label' => 'Gateway fees', 'delta' => -$t['gateway_fees']],
        ];

        $series = [];
        foreach ($steps as $step) {
            $start = $running;
            $running += $step['delta'];
            $series[] = [...$step, 'start' => $start, 'end' => $running];
        }

        $series[] = ['key' => 'contribution_margin', 'label' => 'Contribution Margin', 'delta' => $running, 'start' => 0, 'end' => $running, 'is_total' => true];

        return $series;
    }

    /**
     * The guided "Sales Journey" walkthrough — the same chain, one narrated
     * step at a time.
     *
     * @return list<array<string, mixed>>
     */
    public function journey(WidgetFilters $filters): array
    {
        $t = $this->rollups->totals($filters);

        return [
            [
                'step' => 1, 'key' => 'gross_sales', 'title' => 'You sold this much',
                'value' => $t['gross_sales'],
                'narrative' => 'Across '.number_format($t['orders_count']).' orders, customers agreed to pay this before anything came off.',
            ],
            [
                'step' => 2, 'key' => 'discounts', 'title' => 'Discounts came off first',
                'value' => -$t['discounts'],
                'narrative' => Num::pct($t['discounts'], $t['gross_sales']).'% of gross went to discounts.',
            ],
            [
                'step' => 3, 'key' => 'cancelled', 'title' => 'Some orders never happened',
                'value' => -$t['cancelled_amount'],
                'narrative' => number_format($t['cancelled_orders']).' orders were cancelled before invoicing.',
            ],
            [
                'step' => 4, 'key' => 'invoiced_sales', 'title' => 'This is what you invoiced',
                'value' => $t['invoiced_sales'], 'is_milestone' => true,
                'narrative' => 'The amount you actually billed customers.',
            ],
            [
                'step' => 5, 'key' => 'returns', 'title' => 'Then customers sent things back',
                'value' => -($t['returned_amount'] + $t['rto_amount']),
                'narrative' => number_format($t['returned_orders']).' returns and '.number_format($t['rto_orders']).' RTOs came off the top.',
            ],
            [
                'step' => 6, 'key' => 'net_sales', 'title' => 'Net sales — revenue you keep',
                'value' => $t['net_sales'], 'is_milestone' => true,
                'narrative' => Num::pct($t['net_sales'], $t['gross_sales']).'% of gross survived to net.',
            ],
            [
                'step' => 7, 'key' => 'cogs', 'title' => 'Product cost',
                'value' => -$t['cogs'],
                'narrative' => 'COGS is '.Num::pct($t['cogs'], $t['net_sales']).'% of net sales.',
            ],
            [
                'step' => 8, 'key' => 'costs', 'title' => 'Fees, logistics, packaging and gateway',
                'value' => -($t['marketplace_fees'] + $t['logistics_cost'] + $t['packaging_cost'] + $t['gateway_fees'] + $t['return_cost']),
                'narrative' => 'Everything it costs to get the product to the customer and get paid.',
            ],
            [
                'step' => 9, 'key' => 'contribution_margin', 'title' => 'Contribution margin',
                'value' => $t['contribution_margin'], 'is_milestone' => true,
                'narrative' => 'You keep '.Num::pct($t['contribution_margin'], $t['net_sales']).'% of net sales before fixed costs and ad spend.',
            ],
        ];
    }

    /** @param array<string, int> $t */
    private function verdict(array $t, float $leakagePct): Verdict
    {
        if ($t['gross_sales'] === 0) {
            return Verdict::neutral('No sales in this window.');
        }

        $marginPct = Num::pct($t['contribution_margin'], $t['net_sales']);
        $biggestLeak = collect([
            'discounts' => $t['discounts'],
            'cancellations' => $t['cancelled_amount'],
            'returns' => $t['returned_amount'],
            'RTO' => $t['rto_amount'],
        ])->sortDesc();

        if ($marginPct < 0) {
            return Verdict::bad(
                'You are losing money on every rupee of net sales.',
                sprintf('Contribution margin is %.1f%%. The biggest leak is %s at %s.', $marginPct, $biggestLeak->keys()->first(), Money::compact((int) $biggestLeak->first())),
                'Fix '.$biggestLeak->keys()->first().' before spending another rupee on ads.',
                abs($t['contribution_margin']),
            );
        }

        if ($leakagePct > 25) {
            return Verdict::watch(
                sprintf('%.1f%% of gross never reaches net sales.', $leakagePct),
                sprintf('%s alone accounts for %s.', $biggestLeak->keys()->first(), Money::compact((int) $biggestLeak->first())),
                'Attack '.$biggestLeak->keys()->first().' first — it is the largest single leak.',
            );
        }

        return Verdict::good(
            sprintf('%.1f%% of gross survives to net sales.', 100 - $leakagePct),
            sprintf('Contribution margin is %.1f%% of net sales.', $marginPct),
        );
    }

    /** @return array<string, mixed> */
    private function row(string $key, string $label, int $orders, int $amount, string $kind, string $tooltip): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'orders' => $orders,
            'amount' => $amount,
            'kind' => $kind,
            'tooltip' => $tooltip,
        ];
    }
}
