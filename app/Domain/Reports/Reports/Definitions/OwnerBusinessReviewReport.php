<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Domain\Sales\Queries\HealthFlagsQuery;
use App\Domain\Sales\Queries\SalesSummaryQuery;
use App\Models\Benchmark;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * The one screen an owner reads on a Monday: what happened, how it compares to
 * the period before, and the single thing to fix first.
 */
class OwnerBusinessReviewReport extends Report
{
    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly SalesSummaryQuery $summary,
        private readonly HealthFlagsQuery $flags,
    ) {}

    public function key(): string
    {
        return 'owner_business_review';
    }

    public function label(): string
    {
        return 'Owner Business Review';
    }

    public function category(): string
    {
        return 'Executive';
    }

    public function description(): string
    {
        return 'One-screen scorecard against the previous period.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['sales_summary', 'channel_scorecard'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $current = $this->rollups->totals($filters);
        $previous = $this->rollups->totals($filters->previous());
        $spend = $this->rollups->adSpend($filters)['total'];
        $previousSpend = $this->rollups->adSpend($filters->previous())['total'];

        $netProfit = $current['contribution_margin'] - $spend;
        $previousNetProfit = $previous['contribution_margin'] - $previousSpend;

        $daily = $this->rollups->daily($filters, ['net_sales', 'contribution_margin', 'orders_count']);

        $kpis = [
            $this->kpi('net_sales', 'Net Sales', (float) $current['net_sales'], (float) $previous['net_sales'],
                tooltip: 'Invoiced sales after returns and RTO — revenue you actually keep.'),
            $this->kpi('contribution_margin', 'Contribution Margin', (float) $current['contribution_margin'], (float) $previous['contribution_margin'],
                tooltip: 'Net sales minus COGS, fees, logistics, packaging and gateway charges.'),
            $this->kpi('margin_pct', 'Margin %', $this->marginPct($current), $this->marginPct($previous), 'percent',
                tooltip: 'Contribution margin as a share of net sales.'),
            $this->kpi('ad_spend', 'Ad Spend', (float) $spend, (float) $previousSpend, higherIsBetter: false),
            $this->kpi('net_profit', 'Net Profit', (float) $netProfit, (float) $previousNetProfit,
                tooltip: 'Contribution margin minus ad spend. Fixed opex is not deducted here — see the P&L.'),
            $this->kpi('orders', 'Orders', (float) $current['orders_count'], (float) $previous['orders_count'], 'number'),
            $this->kpi('aov', 'AOV', (float) Num::safeDivide($current['net_sales'], $current['orders_count']),
                (float) Num::safeDivide($previous['net_sales'], $previous['orders_count'])),
            $this->kpi('rto_rate', 'RTO Rate', Num::pct($current['rto_amount'], $current['invoiced_sales']),
                Num::pct($previous['rto_amount'], $previous['invoiced_sales']), 'percent', higherIsBetter: false),
        ];

        $flags = $this->flags->handle($filters);
        $summary = $this->summary->handle($filters);

        return new ReportPayload(
            kpis: $kpis,
            sections: [
                Section::waterfall(
                    'Where the money went',
                    array_map(static fn (array $step): array => [
                        'label' => $step['label'],
                        'value' => $step['delta'],
                        'type' => ($step['is_total'] ?? false) ? 'total' : 'delta',
                        'start' => $step['start'],
                        'end' => $step['end'],
                    ], $this->summary->waterfall($filters)),
                    'Gross sales down to contribution margin, in the order the money leaves.',
                ),
                Section::chart(Section::LINE, 'Net sales and margin by day', $daily->all(), 'date', [
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency'],
                    ['key' => 'contribution_margin', 'label' => 'Contribution margin', 'format' => 'currency'],
                ]),
                $this->channelSection($filters),
                Section::table(
                    'What needs attention',
                    array_map(static fn (array $flag): array => [
                        'title' => $flag['title'],
                        'body' => $flag['body'],
                        'severity' => $flag['severity'],
                        'impact_amount' => $flag['impact_amount'],
                    ], $flags),
                    [
                        ['key' => 'severity', 'label' => 'Severity', 'format' => 'badge'],
                        ['key' => 'title', 'label' => 'Signal', 'format' => 'text'],
                        ['key' => 'body', 'label' => 'What it means', 'format' => 'text'],
                        ['key' => 'impact_amount', 'label' => 'Estimated impact', 'format' => 'currency', 'align' => 'right'],
                    ],
                    $flags === [] ? 'Nothing tripped in this window.' : 'Health checks that tripped in this window.',
                ),
            ],
            verdict: $this->verdict($current, $previous, $netProfit, $previousNetProfit, (int) $spend),
            caveats: array_filter([
                $summary['totals']['orders_count'] === 0
                    ? Caveat::partial('No orders fell in this window, so every comparison below is against zero.')
                    : null,
                $spend === 0
                    ? Caveat::note('No ad spend is recorded for this window, so net profit equals contribution margin.')
                    : null,
            ]),
        );
    }

    private function channelSection(WidgetFilters $filters): Section
    {
        $rows = $this->rollups->byChannel($filters)->map(static fn (object $row): array => [
            'channel' => $row->channel_name,
            'net_sales' => (int) $row->net_sales,
            'orders' => (int) $row->orders_count,
            'margin' => (int) $row->contribution_margin,
            'margin_pct' => Num::pct((int) $row->contribution_margin, (int) $row->net_sales),
        ])->all();

        return Section::table('Channel contribution', $rows, [
            ['key' => 'channel', 'label' => 'Channel', 'format' => 'text'],
            ['key' => 'orders', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
            ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'margin', 'label' => 'Contribution margin', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'margin_pct', 'label' => 'Margin %', 'format' => 'percent', 'align' => 'right'],
        ], exportKey: 'channel_scorecard', config: ['drilldown' => ['dimension' => 'channel', 'value_key' => 'channel']]);
    }

    /**
     * @param  array<string, int>  $current
     * @param  array<string, int>  $previous
     */
    private function verdict(array $current, array $previous, int $netProfit, int $previousNetProfit, int $spend): Verdict
    {
        $target = (float) Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()])->target_margin_pct;
        $marginPct = $this->marginPct($current);
        $delta = $netProfit - $previousNetProfit;

        if ($netProfit <= 0) {
            return Verdict::bad(
                'You are losing money at the contribution line',
                sprintf('Contribution margin %s does not cover ad spend %s.', Money::format($current['contribution_margin']), Money::format($spend)),
                'Cut the worst-ROAS campaign and the loss-making SKUs before adding budget anywhere.',
                abs($netProfit),
            );
        }

        if ($marginPct < $target) {
            return Verdict::watch(
                sprintf('Profitable, but margin %.1f%% is under your %.1f%% target', $marginPct, $target),
                sprintf('Net profit %s (%s vs the previous period).', Money::format($netProfit), $delta >= 0 ? '+'.Money::format($delta) : Money::format($delta)),
                'Work the fee leakage and discount impact reports — that is where the gap usually sits.',
            );
        }

        return Verdict::good(
            sprintf('Healthy period: %s net profit at %.1f%% margin', Money::format($netProfit), $marginPct),
            sprintf('Net sales %s, %s the previous period.', Money::format($current['net_sales']),
                $current['net_sales'] >= $previous['net_sales'] ? 'up on' : 'down on'),
            'Scale what is working — check the channel table for where margin is strongest.',
        );
    }
}
