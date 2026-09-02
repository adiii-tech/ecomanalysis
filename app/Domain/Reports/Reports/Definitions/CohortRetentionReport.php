<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Customers\Queries\CohortQuery;
use App\Domain\Customers\Queries\CustomerQuery;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Support\Caveat;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * Do the customers you bought last month still come back? Retention by
 * acquisition cohort, which is the only honest way to read repeat rate.
 */
class CohortRetentionReport extends Report
{
    public function __construct(
        private readonly CohortQuery $cohorts,
        private readonly CustomerQuery $customers,
    ) {}

    public function key(): string
    {
        return 'cohort_retention';
    }

    public function label(): string
    {
        return 'Cohort Retention';
    }

    public function category(): string
    {
        return 'Marketing & Customers';
    }

    public function description(): string
    {
        return 'Acquisition month by month repeat rate.';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['cohort_retention'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $heatmap = $this->cohorts->heatmap(12);
        $repeat = $this->customers->repeatMetrics();
        $summary = $this->cohorts->summary();

        $curve = collect($summary['series'])->map(static fn (array $row): array => [
            'month' => 'M'.$row['month_index'],
            'retention_pct' => $row['avg_retention_pct'],
            'avg_ltv' => $row['avg_ltv'],
        ])->all();

        return new ReportPayload(
            kpis: [
                $this->kpi('repeat_rate', 'Repeat Purchase Rate', (float) $repeat['repeat_purchase_rate'], null, 'percent'),
                $this->kpi('second_order_90d', 'Second order within 90 days', (float) $repeat['second_order_within_90d_pct'], null, 'percent',
                    tooltip: 'Share of customers who came back inside the first quarter — the strongest early signal of a healthy brand.'),
                $this->kpi('avg_orders', 'Orders per Customer', (float) $repeat['avg_orders_per_customer'], null, 'ratio'),
                $this->kpi('days_to_second', 'Days to Second Order', (float) $repeat['avg_days_to_second_order'], null, 'days', higherIsBetter: false),
            ],
            sections: [
                Section::table('Retention heatmap', $this->heatmapRows($heatmap), $this->heatmapColumns($heatmap),
                    'Each row is the month a customer first bought; each column is how many were still buying N months later.',
                    exportKey: 'cohort_retention', config: ['heatmap' => true, 'heatmap_from' => 'm1']),
                Section::chart(Section::LINE, 'Average retention curve', $curve, 'month', [
                    ['key' => 'retention_pct', 'label' => 'Retention %', 'format' => 'percent'],
                ], 'Averaged across your six most recent cohorts.'),
                Section::chart(Section::BAR, 'LTV build-up by month since first order', $curve, 'month', [
                    ['key' => 'avg_ltv', 'label' => 'Average LTV', 'format' => 'currency'],
                ]),
            ],
            verdict: $this->verdictFrom($summary, $heatmap),
            caveats: [Caveat::note($heatmap['caveat'])],
        );
    }

    /**
     * @param  array<string, mixed>  $heatmap
     * @return list<array<string, mixed>>
     */
    private function heatmapRows(array $heatmap): array
    {
        return collect($heatmap['cohorts'])->map(static function (array $cohort): array {
            $row = ['cohort_month' => $cohort['cohort_month'], 'cohort_size' => $cohort['cohort_size']];

            foreach ($cohort['cells'] as $index => $cell) {
                $row['m'.$index] = $cell['retention_pct'] ?? null;
            }

            return $row;
        })->all();
    }

    /**
     * @param  array<string, mixed>  $heatmap
     * @return list<array<string, mixed>>
     */
    private function heatmapColumns(array $heatmap): array
    {
        $columns = [
            ['key' => 'cohort_month', 'label' => 'Cohort', 'format' => 'text'],
            ['key' => 'cohort_size', 'label' => 'Size', 'format' => 'number', 'align' => 'right'],
        ];

        for ($month = 0; $month <= (int) $heatmap['months']; $month++) {
            $columns[] = ['key' => 'm'.$month, 'label' => 'M'.$month, 'format' => 'percent', 'align' => 'right'];
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $heatmap
     */
    private function verdictFrom(array $summary, array $heatmap): Verdict
    {
        $verdict = $summary['verdict'] ?? $heatmap['verdict'] ?? null;

        if (is_array($verdict)) {
            return new Verdict(
                $verdict['status'] ?? Verdict::NEUTRAL,
                $verdict['headline'] ?? 'Retention',
                $verdict['detail'] ?? null,
                $verdict['action'] ?? null,
            );
        }

        return Verdict::neutral('Not enough cohort history', 'Cohorts need at least two full months of orders before retention means anything.');
    }
}
