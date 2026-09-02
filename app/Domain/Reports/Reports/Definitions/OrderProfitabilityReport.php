<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Domain\Sales\Queries\PaymentModeQuery;
use App\Support\Caveat;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * Order-level P&L. Most brands have never seen which individual orders lose
 * money; this is that list, with the pattern behind it.
 */
class OrderProfitabilityReport extends Report
{
    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly DatasetRegistry $datasets,
        private readonly PaymentModeQuery $paymentModes,
    ) {}

    public function key(): string
    {
        return 'order_profitability';
    }

    public function label(): string
    {
        return 'Order Profitability';
    }

    public function category(): string
    {
        return 'Profit & Margin';
    }

    public function description(): string
    {
        return 'Order-level P&L, filterable and exportable.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['order_profitability', 'loss_orders'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $totals = $this->rollups->totals($filters);
        $previous = $this->rollups->totals($filters->previous());

        $dataset = $this->datasets->build('order_profitability', $filters);
        $rows = $dataset->rows->map(static fn (array|object $row): array => (array) $row)->values()->all();

        $lossRows = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['contribution_margin'] ?? 0) < 0));
        $lossAmount = abs($this->sumRows($lossRows, 'contribution_margin'));

        $payment = $this->paymentModes->handle($filters);

        return new ReportPayload(
            kpis: [
                $this->kpi('orders', 'Orders', (float) $totals['orders_count'], (float) $previous['orders_count'], 'number'),
                $this->kpi('margin_per_order', 'Margin per Order',
                    (float) Num::safeDivide($totals['contribution_margin'], $totals['orders_count']),
                    (float) Num::safeDivide($previous['contribution_margin'], $previous['orders_count'])),
                $this->kpi('loss_orders', 'Loss-making Orders', (float) $totals['loss_orders'], (float) $previous['loss_orders'], 'number', higherIsBetter: false),
                $this->kpi('loss_amount', 'Money lost on them', (float) $totals['loss_amount'], (float) $previous['loss_amount'], higherIsBetter: false),
            ],
            sections: [
                Section::table('Loss-making orders first', array_slice($lossRows, 0, 100), $this->columns(),
                    $lossRows === [] ? 'No order in this window lost money.' : sprintf('%d orders finished below zero.', count($lossRows)),
                    exportKey: 'loss_orders', config: ['drilldown' => ['dimension' => 'state', 'value_key' => 'state']]),
                $this->tableFromDataset($dataset, 'Every order in the window', exportKey: 'order_profitability'),
                Section::chart(Section::BAR, 'Margin by payment mode', array_map(static fn (array $row): array => [
                    'mode' => $row['label'] ?? $row['payment_mode'] ?? '—',
                    'net_sales' => (int) ($row['net_sales'] ?? 0),
                    'contribution_margin' => (int) ($row['contribution_margin'] ?? 0),
                ], $payment['rows'] ?? []), 'mode', [
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency'],
                    ['key' => 'contribution_margin', 'label' => 'Contribution margin', 'format' => 'currency'],
                ], 'COD orders carry RTO risk and a collection charge that prepaid orders do not.'),
            ],
            verdict: $this->verdict($totals, $lossRows, $lossAmount),
            caveats: array_filter([
                Caveat::note('Order-level margin allocates shipping, packaging and gateway costs per order using your cost settings. Fixed monthly opex is not allocated here — the P&L report handles that.'),
                count($rows) >= 5000 ? Caveat::partial('Only the first 5,000 orders are listed on screen; the CSV export carries the full window.') : null,
            ]),
        );
    }

    /** @return list<array<string, mixed>> */
    private function columns(): array
    {
        return [
            ['key' => 'order_number', 'label' => 'Order', 'format' => 'text'],
            ['key' => 'placed_at', 'label' => 'Placed', 'format' => 'datetime'],
            ['key' => 'channel', 'label' => 'Channel', 'format' => 'text'],
            ['key' => 'payment_mode', 'label' => 'Payment', 'format' => 'text'],
            ['key' => 'state', 'label' => 'State', 'format' => 'text'],
            ['key' => 'net_amount', 'label' => 'Net', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'cogs_amount', 'label' => 'COGS', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'fees_amount', 'label' => 'Fees', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'logistics_amount', 'label' => 'Logistics', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'contribution_margin', 'label' => 'Margin', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'margin_pct', 'label' => 'Margin %', 'format' => 'percent', 'align' => 'right'],
        ];
    }

    /**
     * @param  array<string, int>  $totals
     * @param  list<array<string, mixed>>  $lossRows
     */
    private function verdict(array $totals, array $lossRows, int $lossAmount): Verdict
    {
        if ($totals['orders_count'] === 0) {
            return Verdict::neutral('No orders', 'Nothing was placed in this window.');
        }

        $lossShare = Num::pct(count($lossRows), $totals['orders_count']);

        if ($lossShare >= 10) {
            $states = collect($lossRows)->groupBy('state')->map->count()->sortDesc();
            $worstState = $states->keys()->first();

            return Verdict::bad(
                sprintf('%.1f%% of your orders lose money', $lossShare),
                sprintf('%s went out the door and came back as a loss%s.',
                    Money::format($lossAmount),
                    $worstState !== null ? ', concentrated in '.$worstState : ''),
                'Set a minimum order value or drop free shipping for the states in that list.',
                $lossAmount,
            );
        }

        if ($lossShare > 0) {
            return Verdict::watch(
                sprintf('%d orders finished below zero', count($lossRows)),
                sprintf('They cost %s, which is %.1f%% of orders.', Money::format($lossAmount), $lossShare),
                'Look at the shared pattern — usually one SKU, one state or one discount code.',
            );
        }

        return Verdict::good(
            'Every order in this window made money',
            sprintf('Average margin per order is %s.', Money::format((int) round(Num::safeDivide($totals['contribution_margin'], $totals['orders_count'])))),
        );
    }
}
