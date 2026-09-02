<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Period;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * Month-to-date actual vs target vs run-rate projection. The projection is a
 * straight-line run rate, and we say so — no forecasting model is claimed here.
 */
class PacingQuery
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /** @return array<string, mixed> */
    public function handle(WidgetFilters $filters): array
    {
        $timezone = Tenant::timezone();
        $mtd = Period::fromPreset('mtd', $timezone);
        $now = now($timezone);
        $daysElapsed = max(1, $mtd->days());
        $daysInMonth = (int) $now->daysInMonth;

        $totals = $this->rollups->totals($filters->withPeriod($mtd));
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);

        $actual = $totals['net_sales'];
        $target = $benchmark->monthly_revenue_target;
        $dailyRate = (int) round(Num::safeDivide($actual, $daysElapsed));
        $projected = $dailyRate * $daysInMonth;

        $lastMonth = Period::fromPreset('last_month', $timezone);
        $lastMonthTotal = $this->rollups->totals($filters->withPeriod($lastMonth))['net_sales'];

        $series = $this->rollups->daily($filters->withPeriod($mtd), ['net_sales']);
        $running = 0;
        $cumulative = $series->map(static function (array $row) use (&$running, $target, $daysInMonth): array {
            $running += (int) $row['net_sales'];
            $dayNumber = (int) date('j', strtotime((string) $row['date']));

            return [
                'date' => $row['date'],
                'actual' => $running,
                'target' => $target > 0 ? (int) round($target / $daysInMonth * $dayNumber) : null,
            ];
        });

        return [
            'actual' => $actual,
            'target' => $target,
            'projected' => $projected,
            'daily_rate' => $dailyRate,
            'days_elapsed' => $daysElapsed,
            'days_in_month' => $daysInMonth,
            'last_month' => $lastMonthTotal,
            'vs_last_month_pct' => Num::pct($projected - $lastMonthTotal, $lastMonthTotal),
            'attainment_pct' => $target > 0 ? Num::pct($actual, $target) : null,
            'projected_attainment_pct' => $target > 0 ? Num::pct($projected, $target) : null,
            'series' => $cumulative->all(),
            'caveat' => 'Projection is a straight-line run rate from month-to-date sales, not a forecast model.',
            'verdict' => $this->verdict($actual, $target, $projected, $lastMonthTotal)->toArray(),
        ];
    }

    private function verdict(int $actual, int $target, int $projected, int $lastMonth): Verdict
    {
        if ($target <= 0) {
            return Verdict::neutral(
                sprintf('On track for %s this month at the current run rate.', Money::compact($projected)),
                'Set a monthly revenue target in Benchmarks to pace against it.',
            );
        }

        $attainment = Num::pct($projected, $target);

        return match (true) {
            $attainment >= 100 => Verdict::good(
                sprintf('Pacing to %.0f%% of target.', $attainment),
                sprintf('%s projected against a %s target.', Money::compact($projected), Money::compact($target)),
            ),
            $attainment >= 85 => Verdict::watch(
                sprintf('Pacing to %.0f%% of target — %s short.', $attainment, Money::compact($target - $projected)),
                sprintf('You need %s a day to close the gap.', Money::compact((int) round(($target - $actual) / max(1, 30)))),
                'Push spend on your highest-ROAS campaign for the rest of the month.',
            ),
            default => Verdict::bad(
                sprintf('Pacing to only %.0f%% of target.', $attainment),
                sprintf('%s projected against a %s target.', Money::compact($projected), Money::compact($target)),
                'The gap is too large to close on run rate alone — the target or the plan needs to change.',
                $target - $projected,
            ),
        };
    }
}
