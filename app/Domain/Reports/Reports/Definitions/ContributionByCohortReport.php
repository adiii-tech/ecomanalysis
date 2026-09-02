<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * Retention tells you who came back; this tells you whether they were worth
 * winning. Contribution margin per acquisition cohort, against what it cost to
 * acquire that cohort in the first place.
 */
class ContributionByCohortReport extends Report
{
    public function key(): string
    {
        return 'contribution_by_cohort';
    }

    public function label(): string
    {
        return 'Contribution by Cohort';
    }

    public function category(): string
    {
        return 'Marketing & Customers';
    }

    public function description(): string
    {
        return 'Profit per acquisition cohort over time.';
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
        $snapshots = DB::table('cohort_snapshots')
            ->where('tenant_id', Tenant::id())
            ->where('month_index', '<=', 12)
            ->orderBy('cohort_month')
            ->orderBy('month_index')
            ->get();

        $spendByMonth = DB::table('ad_spend_rollup')
            ->where('tenant_id', Tenant::id())
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS month, COALESCE(SUM(spend),0) AS spend")
            ->groupByRaw("DATE_FORMAT(date, '%Y-%m')")
            ->pluck('spend', 'month');

        $rows = $snapshots->groupBy('cohort_month')->map(static function ($group, string $month) use ($spendByMonth): array {
            $size = (int) ($group->firstWhere('month_index', 0)->cohort_size ?? $group->first()->cohort_size);
            $cumulativeMargin = (int) $group->sum('margin');
            $cumulativeRevenue = (int) $group->sum('revenue');
            $spend = (int) ($spendByMonth[substr($month, 0, 7)] ?? 0);
            $cac = (int) round(Num::safeDivide($spend, $size));
            $marginPerCustomer = (int) round(Num::safeDivide($cumulativeMargin, $size));

            $row = [
                'cohort_month' => $month,
                'cohort_size' => $size,
                'acquisition_spend' => $spend,
                'cac' => $cac,
                'revenue_to_date' => $cumulativeRevenue,
                'margin_to_date' => $cumulativeMargin,
                'margin_per_customer' => $marginPerCustomer,
                'payback' => $cac === 0 ? null : round($marginPerCustomer / max(1, $cac), 2),
                'months_observed' => $group->max('month_index') + 1,
            ];

            $running = 0;
            foreach ($group->sortBy('month_index') as $cell) {
                $running += (int) $cell->margin;
                $row['m'.$cell->month_index] = (int) round(Num::safeDivide($running, $size));
            }

            return $row;
        })->values()->all();

        $paidBack = array_values(array_filter($rows, static fn (array $row): bool => $row['payback'] !== null && $row['payback'] >= 1));
        $totalMargin = $this->sumRows($rows, 'margin_to_date');
        $totalSpend = $this->sumRows($rows, 'acquisition_spend');

        $curve = $this->curve($rows);

        return new ReportPayload(
            kpis: [
                $this->kpi('cohorts', 'Cohorts tracked', (float) count($rows), null, 'number'),
                $this->kpi('margin_to_date', 'Contribution earned', (float) $totalMargin),
                $this->kpi('acquisition_spend', 'Acquisition spend', (float) $totalSpend, higherIsBetter: false),
                $this->kpi('cohorts_paid_back', 'Cohorts that paid back CAC', (float) count($paidBack), null, 'number'),
            ],
            sections: [
                Section::table('Every cohort, and what it has returned', $rows, $this->columns($rows),
                    'Each M-column is cumulative contribution margin per customer, so you can see the month a cohort crossed its own CAC.'),
                Section::chart(Section::LINE, 'Cumulative margin per customer', $curve, 'month', [
                    ['key' => 'margin_per_customer', 'label' => 'Margin per customer', 'format' => 'currency'],
                    ['key' => 'cac', 'label' => 'Average CAC', 'format' => 'currency'],
                ], 'Where the margin line crosses the CAC line is your payback month.'),
                Section::chart(Section::BAR, 'Contribution by cohort', $rows, 'cohort_month', [
                    ['key' => 'margin_to_date', 'label' => 'Margin to date', 'format' => 'currency'],
                    ['key' => 'acquisition_spend', 'label' => 'Acquisition spend', 'format' => 'currency'],
                ]),
            ],
            verdict: $this->verdict($rows, $paidBack),
            caveats: [
                Caveat::note('Cohorts cover D2C customers only — marketplaces do not share buyer identity.'),
                Caveat::partial('Acquisition spend is the whole month\'s ad spend charged to that month\'s new customers. Platforms do not report which spend won which customer, so CAC here is an upper bound, not an exact figure.'),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function columns(array $rows): array
    {
        $months = 0;
        foreach ($rows as $row) {
            $months = max($months, (int) $row['months_observed']);
        }

        $columns = [
            ['key' => 'cohort_month', 'label' => 'Cohort', 'format' => 'text'],
            ['key' => 'cohort_size', 'label' => 'Customers', 'format' => 'number', 'align' => 'right'],
            ['key' => 'cac', 'label' => 'CAC', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'margin_per_customer', 'label' => 'Margin / customer', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'payback', 'label' => 'Payback ×', 'format' => 'ratio', 'align' => 'right',
                'tooltip' => 'Contribution margin per customer divided by CAC. Below 1.0 means the cohort has not paid for itself yet.'],
        ];

        for ($month = 0; $month < min($months, 13); $month++) {
            $columns[] = ['key' => 'm'.$month, 'label' => 'M'.$month, 'format' => 'currency', 'align' => 'right'];
        }

        return $columns;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function curve(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $averageCac = (int) round(Num::safeDivide($this->sumRows($rows, 'acquisition_spend'), $this->sumRows($rows, 'cohort_size')));
        $curve = [];

        for ($month = 0; $month < 13; $month++) {
            $values = array_values(array_filter(array_map(
                static fn (array $row): ?int => isset($row['m'.$month]) ? (int) $row['m'.$month] : null,
                $rows,
            ), static fn (?int $value): bool => $value !== null));

            if ($values === []) {
                continue;
            }

            $curve[] = [
                'month' => 'M'.$month,
                'margin_per_customer' => (int) round(array_sum($values) / count($values)),
                'cac' => $averageCac,
            ];
        }

        return $curve;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $paidBack
     */
    private function verdict(array $rows, array $paidBack): Verdict
    {
        if ($rows === []) {
            return Verdict::neutral('No cohort history yet', 'Cohorts appear once you have a full month of D2C orders.');
        }

        $unpaid = array_values(array_filter($rows, static fn (array $row): bool => $row['payback'] !== null && $row['payback'] < 1 && (int) $row['months_observed'] >= 3));

        if ($unpaid !== []) {
            $worst = $unpaid[0];

            return Verdict::bad(
                sprintf('The %s cohort still has not paid back its CAC', $worst['cohort_month']),
                sprintf('%s per customer earned against %s spent to acquire them, %d months in.',
                    Money::format((int) $worst['margin_per_customer']), Money::format((int) $worst['cac']), (int) $worst['months_observed']),
                'Either the acquisition price is too high or the second order is not happening — check retention before spending more.',
                ((int) $worst['cac'] - (int) $worst['margin_per_customer']) * (int) $worst['cohort_size'],
            );
        }

        if ($paidBack === []) {
            return Verdict::watch(
                'No cohort has crossed its CAC yet',
                'Either the cohorts are too young to judge, or no ad spend is recorded against them.',
                'Give the newest cohorts three months before drawing a conclusion.',
            );
        }

        return Verdict::good(
            sprintf('%d of %d cohorts have paid back what they cost to acquire', count($paidBack), count($rows)),
            'Contribution margin per customer is running ahead of CAC.',
            'The acquisition maths works — the constraint is how much volume you can buy at this price.',
        );
    }
}
