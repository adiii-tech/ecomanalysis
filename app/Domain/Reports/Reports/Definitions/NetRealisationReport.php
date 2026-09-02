<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

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
 * For every ₹100 a marketplace shows as sales, how much reaches your bank?
 * The gross → net chain, per channel, so the answer is a number and not a
 * feeling.
 */
class NetRealisationReport extends Report
{
    public function __construct(private readonly RollupQuery $rollups) {}

    public function key(): string
    {
        return 'net_realisation';
    }

    public function label(): string
    {
        return 'Net Realisation';
    }

    public function category(): string
    {
        return 'Profit & Margin';
    }

    public function description(): string
    {
        return 'Gross to net waterfall per marketplace after fees, discounts and GST.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['channel_scorecard'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $channels = $this->rollups->byChannel($filters);
        $totals = $this->rollups->totals($filters);
        $previous = $this->rollups->totals($filters->previous());

        $rows = $channels->map(static function (object $row): array {
            $gross = (int) $row->gross_sales;
            $realised = (int) $row->contribution_margin;

            return [
                'channel' => $row->channel_name,
                'gross_sales' => $gross,
                'discounts' => -(int) $row->discounts,
                'cancelled' => -(int) $row->cancelled_amount,
                'returns' => -((int) $row->returned_amount + (int) $row->rto_amount),
                'net_sales' => (int) $row->net_sales,
                'tax' => (int) $row->tax_amount,
                'cogs' => -(int) $row->cogs,
                'fees' => -((int) $row->marketplace_fees + (int) $row->gateway_fees),
                'logistics' => -((int) $row->logistics_cost + (int) $row->packaging_cost),
                'realised' => $realised,
                'realisation_pct' => Num::pct($realised, $gross),
                'per_100' => round(Num::safeDivide($realised * 100, $gross), 2),
            ];
        })->sortByDesc('realisation_pct')->values()->all();

        $best = $rows[0] ?? null;
        $worst = $rows === [] ? null : $rows[count($rows) - 1];

        return new ReportPayload(
            kpis: [
                $this->kpi('gross_sales', 'Gross Sales', (float) $totals['gross_sales'], (float) $previous['gross_sales']),
                $this->kpi('net_sales', 'Net Sales', (float) $totals['net_sales'], (float) $previous['net_sales']),
                $this->kpi('realised', 'Realised (contribution)', (float) $totals['contribution_margin'], (float) $previous['contribution_margin']),
                $this->kpi('realisation_pct', 'Realisation Rate', Num::pct($totals['contribution_margin'], $totals['gross_sales']),
                    Num::pct($previous['contribution_margin'], $previous['gross_sales']), 'percent',
                    tooltip: 'Contribution margin as a share of gross sales — what survives the whole chain.'),
            ],
            sections: [
                Section::waterfall('Gross to realised, all channels', $this->waterfall($totals),
                    'Each step is what came off before the money reached you.'),
                Section::table('Realisation by channel', $rows, [
                    ['key' => 'channel', 'label' => 'Channel', 'format' => 'text'],
                    ['key' => 'gross_sales', 'label' => 'Gross', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'discounts', 'label' => 'Discounts', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'returns', 'label' => 'Returns + RTO', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'cogs', 'label' => 'COGS', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'fees', 'label' => 'Fees', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'logistics', 'label' => 'Logistics', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'realised', 'label' => 'Realised', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'per_100', 'label' => 'Per ₹100 gross', 'format' => 'number', 'align' => 'right',
                        'tooltip' => 'Rupees of contribution margin for every ₹100 the channel reports as sales.'],
                ]),
                Section::chart(Section::BAR, 'Realisation rate by channel', $rows, 'channel', [
                    ['key' => 'realisation_pct', 'label' => 'Realisation %', 'format' => 'percent'],
                ]),
            ],
            verdict: $this->verdict($best, $worst),
            caveats: [
                Caveat::note('GST is shown as reported on the order. Under inclusive pricing it is already inside gross, so it is not subtracted again here.'),
            ],
        );
    }

    /**
     * @param  array<string, int>  $totals
     * @return list<array{label: string, value: int, type: string}>
     */
    private function waterfall(array $totals): array
    {
        $steps = [
            ['label' => 'Gross', 'value' => $totals['gross_sales'], 'type' => 'delta'],
            ['label' => 'Discounts', 'value' => -$totals['discounts'], 'type' => 'delta'],
            ['label' => 'Cancelled', 'value' => -$totals['cancelled_amount'], 'type' => 'delta'],
            ['label' => 'Returns', 'value' => -$totals['returned_amount'], 'type' => 'delta'],
            ['label' => 'RTO', 'value' => -$totals['rto_amount'], 'type' => 'delta'],
            ['label' => 'COGS', 'value' => -$totals['cogs'], 'type' => 'delta'],
            ['label' => 'Marketplace fees', 'value' => -$totals['marketplace_fees'], 'type' => 'delta'],
            ['label' => 'Gateway fees', 'value' => -$totals['gateway_fees'], 'type' => 'delta'],
            ['label' => 'Logistics', 'value' => -$totals['logistics_cost'], 'type' => 'delta'],
            ['label' => 'Packaging', 'value' => -$totals['packaging_cost'], 'type' => 'delta'],
        ];

        $running = 0;
        $series = [];

        foreach ($steps as $step) {
            $start = $running;
            $running += $step['value'];
            $series[] = [...$step, 'start' => $start, 'end' => $running];
        }

        $series[] = ['label' => 'Realised', 'value' => $running, 'type' => 'total', 'start' => 0, 'end' => $running];

        return $series;
    }

    /**
     * @param  array<string, mixed>|null  $best
     * @param  array<string, mixed>|null  $worst
     */
    private function verdict(?array $best, ?array $worst): Verdict
    {
        if ($best === null) {
            return Verdict::neutral('Nothing sold', 'No channel reported sales in this window.');
        }

        if ($worst !== null && (float) $worst['realisation_pct'] < 5 && $worst['channel'] !== $best['channel']) {
            return Verdict::bad(
                sprintf('%s keeps only ₹%s of every ₹100', $worst['channel'], number_format((float) $worst['per_100'], 2)),
                sprintf('%s gross became %s of contribution.', Money::format((int) $worst['gross_sales']), Money::format((int) $worst['realised'])),
                sprintf('Compare its fee lines against %s before you push more volume there.', $best['channel']),
                (int) $worst['gross_sales'] - (int) $worst['realised'],
            );
        }

        return Verdict::good(
            sprintf('%s realises best at ₹%s per ₹100', $best['channel'], number_format((float) $best['per_100'], 2)),
            sprintf('Blended realisation across channels is %.1f%%.', (float) $best['realisation_pct']),
            'Weight your inventory and promotions toward the top of this table.',
        );
    }
}
