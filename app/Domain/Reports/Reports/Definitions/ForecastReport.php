<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Operations\Queries\InventoryQuery;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Domain\Sales\Queries\PacingQuery;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Period;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * A 30/60/90-day projection built from your own run rate and weekday pattern —
 * arithmetic you could check by hand, not a model you have to trust. It says
 * so plainly, because a forecast you cannot audit is worse than none.
 */
class ForecastReport extends Report
{
    private const HISTORY_DAYS = 90;

    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly InventoryQuery $inventory,
        private readonly PacingQuery $pacing,
    ) {}

    public function key(): string
    {
        return 'forecast';
    }

    public function label(): string
    {
        return 'Forecast';
    }

    public function category(): string
    {
        return 'Executive';
    }

    public function description(): string
    {
        return '30/60/90-day sales and inventory projection.';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $timezone = Tenant::timezone();
        $history = $filters->withPeriod(Period::make(
            now($timezone)->subDays(self::HISTORY_DAYS)->toDateString(),
            now($timezone)->subDay()->toDateString(),
            $timezone,
        ));

        $daily = $this->rollups->daily($history, ['net_sales', 'contribution_margin', 'orders_count']);
        $observedDays = max(1, $daily->count());

        $dailySales = (int) round(Num::safeDivide((int) $daily->sum('net_sales'), $observedDays));
        $dailyMargin = (int) round(Num::safeDivide((int) $daily->sum('contribution_margin'), $observedDays));
        $dailyOrders = round(Num::safeDivide((int) $daily->sum('orders_count'), $observedDays), 2);

        $trend = $this->trend($daily->all());
        $weekday = $this->weekdayPattern($daily->all());
        $pacing = $this->pacing->handle($filters);

        $horizons = [];
        foreach ([30, 60, 90] as $days) {
            $horizons[] = [
                'horizon' => $days.' days',
                'orders' => (int) round($dailyOrders * $days),
                'net_sales' => $dailySales * $days,
                'contribution_margin' => $dailyMargin * $days,
                'net_sales_low' => (int) round($dailySales * $days * 0.85),
                'net_sales_high' => (int) round($dailySales * $days * 1.15),
            ];
        }

        $projection = $this->projectionSeries($dailySales, $daily->all());
        $stockouts = $this->stockoutRisk();

        return new ReportPayload(
            kpis: [
                $this->kpi('run_rate', 'Daily run rate', (float) $dailySales,
                    tooltip: sprintf('Average net sales per day across the last %d days.', self::HISTORY_DAYS)),
                $this->kpi('next_30', 'Next 30 days (net sales)', (float) ($dailySales * 30)),
                $this->kpi('next_30_margin', 'Next 30 days (contribution)', (float) ($dailyMargin * 30)),
                $this->kpi('trend', 'Trend vs previous 45 days', $trend, null, 'percent',
                    tooltip: 'Last 45 days against the 45 before them. The projection does not apply this trend — it is shown so you can judge the run rate yourself.'),
            ],
            sections: [
                Section::table('The projection', $horizons, [
                    ['key' => 'horizon', 'label' => 'Horizon', 'format' => 'text'],
                    ['key' => 'orders', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'net_sales_low', 'label' => 'Low (−15%)', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'net_sales', 'label' => 'Run-rate', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'net_sales_high', 'label' => 'High (+15%)', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'contribution_margin', 'label' => 'Contribution', 'format' => 'currency', 'align' => 'right'],
                ], 'The band is a flat ±15%, not a confidence interval — it exists so nobody reads the middle column as a promise.'),
                Section::chart(Section::LINE, 'History and projection', $projection, 'date', [
                    ['key' => 'actual', 'label' => 'Actual', 'format' => 'currency'],
                    ['key' => 'projected', 'label' => 'Projected', 'format' => 'currency'],
                ], 'The last 90 days as recorded, then the run rate carried forward.'),
                Section::chart(Section::BAR, 'Sales by day of week', $weekday, 'day', [
                    ['key' => 'avg_net_sales', 'label' => 'Average net sales', 'format' => 'currency'],
                ], 'Where your week is heavy — useful for timing campaigns and staffing dispatch.'),
                Section::table('SKUs that run out inside the horizon', $stockouts, [
                    ['key' => 'sku_code', 'label' => 'SKU', 'format' => 'text'],
                    ['key' => 'name', 'label' => 'Product', 'format' => 'text'],
                    ['key' => 'stock', 'label' => 'On hand', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'days_of_cover', 'label' => 'Days of cover', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'runs_out_on', 'label' => 'Runs out', 'format' => 'date'],
                    ['key' => 'monthly_revenue', 'label' => 'Revenue at risk / month', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'suggested_reorder_qty', 'label' => 'Suggested reorder', 'format' => 'number', 'align' => 'right'],
                ], 'Sorted by revenue at risk. A forecast is only useful if you can actually ship the demand.'),
            ],
            verdict: $this->verdict($dailySales, $trend, $stockouts, $pacing),
            caveats: [
                Caveat::partial('This is a straight-line run rate with a weekday profile — not a trained forecast. It does not know about your next sale, a festival, or a stockout.'),
                Caveat::note(sprintf('Built from the last %d days of rollups. Shorter history makes it less reliable, not more precise.', self::HISTORY_DAYS)),
            ],
        );
    }

    /** @param list<array<string, mixed>> $daily */
    private function trend(array $daily): float
    {
        $count = count($daily);

        if ($count < 30) {
            return 0.0;
        }

        $half = (int) floor($count / 2);
        $earlier = array_sum(array_map(static fn (array $row): int => (int) $row['net_sales'], array_slice($daily, 0, $half)));
        $recent = array_sum(array_map(static fn (array $row): int => (int) $row['net_sales'], array_slice($daily, $half)));

        return Num::pct($recent - $earlier, $earlier);
    }

    /**
     * @param  list<array<string, mixed>>  $daily
     * @return list<array<string, mixed>>
     */
    private function weekdayPattern(array $daily): array
    {
        $buckets = [];

        foreach ($daily as $row) {
            $day = date('D', strtotime((string) $row['date']));
            $buckets[$day][] = (int) $row['net_sales'];
        }

        $order = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $rows = [];

        foreach ($order as $day) {
            $values = $buckets[$day] ?? [];
            $rows[] = [
                'day' => $day,
                'avg_net_sales' => $values === [] ? 0 : (int) round(array_sum($values) / count($values)),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $daily
     * @return list<array<string, mixed>>
     */
    private function projectionSeries(int $dailySales, array $daily): array
    {
        $series = array_map(static fn (array $row): array => [
            'date' => $row['date'],
            'actual' => (int) $row['net_sales'],
            'projected' => null,
        ], $daily);

        $last = $daily === [] ? now(Tenant::timezone())->toDateString() : (string) $daily[count($daily) - 1]['date'];

        // The join point carries both series so the chart lines meet instead of
        // showing a gap between history and projection.
        if ($series !== []) {
            $series[count($series) - 1]['projected'] = (int) $series[count($series) - 1]['actual'];
        }

        for ($day = 1; $day <= 90; $day++) {
            $series[] = [
                'date' => date('Y-m-d', strtotime($last.' +'.$day.' day')),
                'actual' => null,
                'projected' => $dailySales,
            ];
        }

        return $series;
    }

    /** @return list<array<string, mixed>> */
    private function stockoutRisk(): array
    {
        $timezone = Tenant::timezone();

        $filters = new WidgetFilters(Period::fromPreset('last_30_days', $timezone));

        return $this->inventory
            ->coverage($filters)
            ->filter(static fn (array $row): bool => $row['days_of_cover'] > 0 && $row['days_of_cover'] <= 90)
            ->sortByDesc('monthly_revenue')
            ->take(50)
            ->map(static fn (array $row): array => [
                ...$row,
                'runs_out_on' => now(Tenant::timezone())->addDays((int) floor($row['days_of_cover']))->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $stockouts
     * @param  array<string, mixed>  $pacing
     */
    private function verdict(int $dailySales, float $trend, array $stockouts, array $pacing): Verdict
    {
        if ($dailySales === 0) {
            return Verdict::neutral('Not enough history to project', 'No sales in the last 90 days to run a rate from.');
        }

        $imminent = array_values(array_filter($stockouts, static fn (array $row): bool => (float) $row['days_of_cover'] <= 30));

        if ($imminent !== []) {
            $atRisk = array_sum(array_map(static fn (array $row): int => (int) $row['monthly_revenue'], $imminent));

            return Verdict::bad(
                sprintf('%d SKUs run out within 30 days', count($imminent)),
                sprintf('They carry %s of monthly revenue between them — the projection assumes you can ship it.', Money::format($atRisk)),
                'Raise the purchase orders in the table above before the run rate becomes theoretical.',
                $atRisk,
            );
        }

        if ($trend < -10) {
            return Verdict::watch(
                sprintf('Sales are trending down %.1f%%', abs($trend)),
                sprintf('The run rate of %s a day is being carried forward from a falling base, so the projection is optimistic.', Money::format($dailySales)),
                'Treat the low column as the working number until the trend flattens.',
            );
        }

        return Verdict::good(
            sprintf('On this run rate you land at %s over 30 days', Money::format($dailySales * 30)),
            $trend > 5 ? sprintf('And the last 45 days are running %.1f%% ahead of the 45 before.', $trend) : 'Demand is steady enough to plan against.',
            'Check the stockout table so supply matches the plan.',
        );
    }
}
