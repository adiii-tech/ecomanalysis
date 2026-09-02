<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Caveat;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * Which channel actually earns its place. Ranked by contribution margin, not by
 * revenue — the biggest seller is often not the one making money.
 */
class ChannelScorecardReport extends Report
{
    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'channel_scorecard';
    }

    public function label(): string
    {
        return 'Channel Scorecard';
    }

    public function category(): string
    {
        return 'Profit & Margin';
    }

    public function description(): string
    {
        return 'Revenue, orders, AOV, margin % and RTO ranked by channel.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['channel_scorecard'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $channels = $this->rollups->byChannel($filters);
        $previous = $this->rollups->byChannel($filters->previous())->keyBy('channel_id');

        $totals = $this->rollups->totals($filters);
        $previousTotals = $this->rollups->totals($filters->previous());

        $best = $channels->sortByDesc(static fn (object $row): int => (int) $row->contribution_margin)->first();
        $worst = $channels->sortBy(static fn (object $row): int => (int) $row->contribution_margin)->first();

        $mix = $channels->map(static fn (object $row): array => [
            'channel' => $row->channel_name,
            'net_sales' => (int) $row->net_sales,
            'contribution_margin' => (int) $row->contribution_margin,
        ])->all();

        $trend = $this->rollups->daily($filters, ['net_sales', 'contribution_margin'])->all();

        return new ReportPayload(
            kpis: [
                $this->kpi('channels', 'Channels selling', (float) $channels->count(), null, 'number'),
                $this->kpi('net_sales', 'Net Sales', (float) $totals['net_sales'], (float) $previousTotals['net_sales']),
                $this->kpi('margin_pct', 'Blended Margin %', $this->marginPct($totals), $this->marginPct($previousTotals), 'percent'),
                $this->kpi('best_channel_margin', 'Best channel margin',
                    $best !== null ? Num::pct((int) $best->contribution_margin, (int) $best->net_sales) : 0.0,
                    null, 'percent', tooltip: $best !== null ? $best->channel_name : null),
            ],
            sections: [
                $this->tableFromDataset(
                    $this->datasets->build('channel_scorecard', $filters),
                    'Every channel, ranked by contribution margin',
                    exportKey: 'channel_scorecard',
                    drilldown: ['dimension' => 'channel', 'value_key' => 'name'],
                ),
                Section::chart(Section::BAR, 'Net sales vs contribution margin', $mix, 'channel', [
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency'],
                    ['key' => 'contribution_margin', 'label' => 'Contribution margin', 'format' => 'currency'],
                ], 'A tall revenue bar with a short margin bar is a channel you are buying, not selling on.'),
                Section::chart(Section::LINE, 'Daily trend', $trend, 'date', [
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency'],
                    ['key' => 'contribution_margin', 'label' => 'Contribution margin', 'format' => 'currency'],
                ]),
                Section::table('Movement vs the previous period', $channels->map(static function (object $row) use ($previous): array {
                    $before = $previous->get($row->channel_id);

                    return [
                        'channel' => $row->channel_name,
                        'net_sales' => (int) $row->net_sales,
                        'net_sales_prev' => $before !== null ? (int) $before->net_sales : 0,
                        'net_sales_delta_pct' => $before !== null ? Num::pct((int) $row->net_sales - (int) $before->net_sales, (int) $before->net_sales) : null,
                        'margin_pct' => Num::pct((int) $row->contribution_margin, (int) $row->net_sales),
                        'margin_pct_prev' => $before !== null ? Num::pct((int) $before->contribution_margin, (int) $before->net_sales) : null,
                    ];
                })->all(), [
                    ['key' => 'channel', 'label' => 'Channel', 'format' => 'text'],
                    ['key' => 'net_sales_prev', 'label' => 'Net sales (prev)', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'net_sales_delta_pct', 'label' => 'Change', 'format' => 'delta_percent', 'align' => 'right'],
                    ['key' => 'margin_pct_prev', 'label' => 'Margin % (prev)', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'margin_pct', 'label' => 'Margin %', 'format' => 'percent', 'align' => 'right'],
                ]),
            ],
            verdict: $this->verdict($best, $worst),
            caveats: $channels->isEmpty()
                ? [Caveat::partial('No channel recorded a sale in this window.')]
                : [],
        );
    }

    private function verdict(?object $best, ?object $worst): Verdict
    {
        if ($best === null) {
            return Verdict::neutral('No channel data', 'Nothing sold in this window.');
        }

        $bestMargin = Num::pct((int) $best->contribution_margin, (int) $best->net_sales);

        if ($worst !== null && (int) $worst->contribution_margin < 0 && $worst->channel_id !== $best->channel_id) {
            return Verdict::bad(
                sprintf('%s is losing money', $worst->channel_name),
                sprintf('It gave back %s on %s of net sales, while %s earned %s.',
                    Money::format(abs((int) $worst->contribution_margin)), Money::format((int) $worst->net_sales),
                    $best->channel_name, Money::format((int) $best->contribution_margin)),
                sprintf('Check fee leakage and RTO on %s before you ship another order there.', $worst->channel_name),
                abs((int) $worst->contribution_margin),
            );
        }

        return Verdict::good(
            sprintf('%s is your profit engine at %.1f%% margin', $best->channel_name, $bestMargin),
            sprintf('It contributed %s this window.', Money::format((int) $best->contribution_margin)),
            'Push inventory and budget toward it before chasing new channels.',
        );
    }
}
