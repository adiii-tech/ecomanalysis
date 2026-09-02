<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Domain\Sales\Queries\GeoQuery;
use App\Models\Benchmark;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * Which states deserve more of the budget. Ad platforms do not report spend by
 * state, so this ranks on realised margin and RTO rather than pretending to
 * know a state-level ROAS.
 */
class StateRoiReport extends Report
{
    public function __construct(
        private readonly GeoQuery $geo,
        private readonly RollupQuery $rollups,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'state_roi';
    }

    public function label(): string
    {
        return 'State ROI';
    }

    public function category(): string
    {
        return 'Marketing & Customers';
    }

    public function description(): string
    {
        return 'Revenue, spend, ROAS and CAC by state.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['state_roi'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $threshold = (float) Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()])->rto_threshold_pct;
        $matrix = $this->geo->actionMatrix($filters, $threshold);
        $dataset = $this->datasets->build('state_roi', $filters);

        $spend = $this->rollups->adSpend($filters)['total'];
        $totals = $this->rollups->totals($filters);
        $states = $this->geo->states($filters, 100);
        $totalOrders = (int) $states->sum('orders');

        $rows = collect($matrix['rows'] ?? [])->all();
        $quadrants = collect($rows)->groupBy('quadrant')->map->count();

        return new ReportPayload(
            kpis: [
                $this->kpi('blended_roas', 'Blended ROAS', (float) Num::ratio($totals['net_sales'], $spend), null, 'ratio',
                    tooltip: 'Net sales divided by total ad spend. Platforms do not break spend down by state, so this is a national figure.'),
                $this->kpi('blended_cac', 'Blended CAC', (float) Num::safeDivide($spend, $totalOrders),
                    tooltip: 'Ad spend per order across all states.'),
                $this->kpi('scale_states', 'States worth scaling', (float) ($quadrants['scale'] ?? 0), null, 'number'),
                $this->kpi('fix_states', 'States to fix or restrict', (float) (($quadrants['fix'] ?? 0) + ($quadrants['restrict'] ?? 0)), null, 'number', higherIsBetter: false),
            ],
            sections: [
                $this->tableFromDataset($dataset, 'Every state, with a recommended action', exportKey: 'state_roi',
                    drilldown: ['dimension' => 'state', 'value_key' => 'state']),
                Section::chart(Section::BAR, 'Margin % against RTO % by state', array_slice($rows, 0, 20), 'state', [
                    ['key' => 'margin_pct', 'label' => 'Margin %', 'format' => 'percent'],
                    ['key' => 'rto_pct', 'label' => 'RTO %', 'format' => 'percent'],
                ], 'Where the RTO bar overtakes the margin bar, the state is selling at a loss.'),
                Section::callouts('The four quadrants', [
                    ['label' => 'Scale', 'value' => $quadrants['scale'] ?? 0, 'format' => 'number', 'tone' => 'good', 'note' => 'High volume, RTO under control.'],
                    ['label' => 'Grow', 'value' => $quadrants['grow'] ?? 0, 'format' => 'number', 'note' => 'Clean delivery, low volume — worth a geo test.'],
                    ['label' => 'Fix', 'value' => $quadrants['fix'] ?? 0, 'format' => 'number', 'tone' => 'bad', 'note' => 'Big enough that its RTO costs real money.'],
                    ['label' => 'Restrict', 'value' => $quadrants['restrict'] ?? 0, 'format' => 'number', 'tone' => 'bad', 'note' => 'Low volume and high RTO — prepaid only.'],
                ]),
            ],
            verdict: $this->verdict($rows, $threshold),
            caveats: [
                Caveat::partial('Meta and Google do not report ad spend by state, so no state-level ROAS or CAC is shown. The blended figures above are national; the ranking below uses realised margin, which is measured.'),
            ],
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function verdict(array $rows, float $threshold): Verdict
    {
        if ($rows === []) {
            return Verdict::neutral('No state data', 'No orders carried a shipping state in this window.');
        }

        $bleeding = collect($rows)
            ->filter(static fn (array $row): bool => (float) $row['rto_pct'] > $threshold && (int) $row['orders'] >= 20)
            ->sortByDesc('net_sales')
            ->first();

        if ($bleeding !== null) {
            return Verdict::bad(
                sprintf('%s is above your %.0f%% RTO limit', $bleeding['state'], $threshold),
                sprintf('%.1f%% RTO on %s of net sales.', (float) $bleeding['rto_pct'], Money::format((int) $bleeding['net_sales'])),
                'Make that state prepaid-only, or exclude it from COD campaigns until RTO comes down.',
            );
        }

        $best = collect($rows)->sortByDesc('margin_pct')->first();

        return Verdict::good(
            sprintf('%s is your best state to scale', $best['state'] ?? '—'),
            sprintf('%.1f%% margin with %.1f%% RTO.', (float) ($best['margin_pct'] ?? 0), (float) ($best['rto_pct'] ?? 0)),
            'Weight the next budget increase toward it.',
        );
    }
}
