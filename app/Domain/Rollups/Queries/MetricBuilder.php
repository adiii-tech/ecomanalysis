<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Queries;

use App\Support\Metric;
use App\Support\Num;
use App\Support\WidgetFilters;
use Illuminate\Support\Collection;

/**
 * Turns rollup totals into KPI payloads: value, previous value, delta,
 * goodness and a sparkline, in one place so every module's KPI strip behaves
 * identically.
 */
class MetricBuilder
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /**
     * @param  array<string, int>  $current
     * @param  array<string, int>  $previous
     * @param  Collection<int, array<string, mixed>>  $series
     */
    public function make(
        string $key,
        string $label,
        array $current,
        array $previous,
        Collection $series,
        string $column,
        string $format = 'currency',
        bool $higherIsBetter = true,
        ?string $tooltip = null,
        ?string $drilldown = null,
    ): Metric {
        return new Metric(
            key: $key,
            label: $label,
            value: (float) ($current[$column] ?? 0),
            previousValue: (float) ($previous[$column] ?? 0),
            format: $format,
            higherIsBetter: $higherIsBetter,
            tooltip: $tooltip,
            sparkline: $this->sparkline($series, $column),
            drilldown: $drilldown,
        );
    }

    /**
     * Derived KPI (a rate, ratio or percentage) that has no single rollup column.
     *
     * @param  Collection<int, array<string, mixed>>  $series
     * @param  callable(array<string, mixed>): float  $compute
     */
    public function derived(
        string $key,
        string $label,
        float $value,
        float $previousValue,
        Collection $series,
        callable $compute,
        string $format = 'percent',
        bool $higherIsBetter = true,
        ?string $tooltip = null,
        ?string $drilldown = null,
        ?string $caveat = null,
    ): Metric {
        return new Metric(
            key: $key,
            label: $label,
            value: $value,
            previousValue: $previousValue,
            format: $format,
            higherIsBetter: $higherIsBetter,
            tooltip: $tooltip,
            sparkline: $series->map(static fn (array $row): array => [
                'date' => (string) $row['date'],
                'value' => round($compute($row), 2),
            ])->all(),
            caveat: $caveat,
            drilldown: $drilldown,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $series
     * @return list<array{date: string, value: float}>
     */
    public function sparkline(Collection $series, string $column): array
    {
        return $series->map(static fn (array $row): array => [
            'date' => (string) $row['date'],
            'value' => (float) ($row[$column] ?? 0),
        ])->all();
    }

    /**
     * The six numbers every module's KPI strip is built from.
     *
     * @return array{current: array<string, int>, previous: array<string, int>, series: Collection<int, non-empty-array<string, int|string>>}
     */
    public function context(WidgetFilters $filters): array
    {
        return [
            'current' => $this->rollups->totals($filters),
            'previous' => $this->rollups->totals($filters->previous()),
            'series' => $this->rollups->daily($filters),
        ];
    }

    /** @param array<string, int> $totals */
    public function marginPct(array $totals): float
    {
        return Num::pct($totals['contribution_margin'] ?? 0, $totals['net_sales'] ?? 0);
    }

    /** @param array<string, int> $totals */
    public function aov(array $totals): float
    {
        return round(Num::safeDivide($totals['net_sales'] ?? 0, $totals['orders_count'] ?? 0));
    }

    /** @param array<string, int> $totals */
    public function returnRate(array $totals): float
    {
        return Num::pct($totals['returned_orders'] ?? 0, $totals['invoiced_orders'] ?? 0);
    }

    /** @param array<string, int> $totals */
    public function rtoRate(array $totals): float
    {
        return Num::pct($totals['rto_orders'] ?? 0, $totals['invoiced_orders'] ?? 0);
    }

    /** @param array<string, int> $totals */
    public function repeatRate(array $totals): float
    {
        return Num::pct($totals['repeat_customers'] ?? 0, $totals['customers_count'] ?? 0);
    }
}
