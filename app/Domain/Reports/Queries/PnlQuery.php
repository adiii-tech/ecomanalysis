<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Models\CostSetting;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Period;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * The true P&L, monthly columns, down to EBITDA.
 *
 * Contribution margin comes from the rollup; ad spend and fixed opex are added
 * here because neither belongs to an individual order.
 */
class PnlQuery
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /** @return array<string, mixed> */
    public function handle(WidgetFilters $filters): array
    {
        $settings = CostSetting::query()->firstOrCreate(['tenant_id' => Tenant::id()]);
        $months = $this->monthsIn($filters);

        $columns = [];
        foreach ($months as $month) {
            $monthFilters = $filters->withPeriod($month['period']);
            $totals = $this->rollups->totals($monthFilters);
            $adSpend = $this->rollups->adSpend($monthFilters)['total'];

            $grossProfit = $totals['net_sales'] - $totals['cogs'];
            $contribution = $totals['contribution_margin'];

            // Only the part of the month that falls inside the window carries
            // its share of the monthly fixed cost.
            $fixedOpex = (int) round($settings->monthly_fixed_opex * $month['opex_share']);
            $ebitda = $contribution - $adSpend - $fixedOpex;

            $columns[] = [
                'month' => $month['label'],
                'key' => $month['key'],
                'gross_sales' => $totals['gross_sales'],
                'discounts' => -$totals['discounts'],
                'cancellations' => -$totals['cancelled_amount'],
                'invoiced_sales' => $totals['invoiced_sales'],
                'returns' => -$totals['returned_amount'],
                'rto' => -$totals['rto_amount'],
                'net_sales' => $totals['net_sales'],
                'cogs' => -$totals['cogs'],
                'gross_profit' => $grossProfit,
                'gross_margin_pct' => Num::pct($grossProfit, $totals['net_sales']),
                'marketplace_fees' => -$totals['marketplace_fees'],
                'payment_gateway_fees' => -$totals['gateway_fees'],
                'logistics' => -$totals['logistics_cost'],
                'return_handling' => -$totals['return_cost'],
                'packaging' => -$totals['packaging_cost'],
                'contribution_margin' => $contribution,
                'contribution_margin_pct' => Num::pct($contribution, $totals['net_sales']),
                'ad_spend' => -$adSpend,
                'fixed_opex' => -$fixedOpex,
                'ebitda' => $ebitda,
                'ebitda_pct' => Num::pct($ebitda, $totals['net_sales']),
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $this->rowDefinitions(),
            'totals' => $this->sumColumns($columns),
            'caveat' => 'Fixed opex is the monthly figure from Cost Settings, pro-rated across the days of each month that fall inside your date range. It is not read from an accounting system.',
            'verdict' => $this->verdict($columns),
        ];
    }

    /** @return list<array{key: string, label: string, kind: string, indent?: bool}> */
    private function rowDefinitions(): array
    {
        return [
            ['key' => 'gross_sales', 'label' => 'Gross Sales', 'kind' => 'positive'],
            ['key' => 'discounts', 'label' => 'Discounts', 'kind' => 'negative', 'indent' => true],
            ['key' => 'cancellations', 'label' => 'Cancellations', 'kind' => 'negative', 'indent' => true],
            ['key' => 'invoiced_sales', 'label' => 'Invoiced Sales', 'kind' => 'subtotal'],
            ['key' => 'returns', 'label' => 'Customer Returns', 'kind' => 'negative', 'indent' => true],
            ['key' => 'rto', 'label' => 'RTO', 'kind' => 'negative', 'indent' => true],
            ['key' => 'net_sales', 'label' => 'Net Sales', 'kind' => 'subtotal'],
            ['key' => 'cogs', 'label' => 'Cost of Goods Sold', 'kind' => 'negative', 'indent' => true],
            ['key' => 'gross_profit', 'label' => 'Gross Profit', 'kind' => 'subtotal'],
            ['key' => 'marketplace_fees', 'label' => 'Marketplace Fees', 'kind' => 'negative', 'indent' => true],
            ['key' => 'payment_gateway_fees', 'label' => 'Payment Gateway Fees', 'kind' => 'negative', 'indent' => true],
            ['key' => 'logistics', 'label' => 'Logistics', 'kind' => 'negative', 'indent' => true],
            ['key' => 'return_handling', 'label' => 'Return & RTO Handling', 'kind' => 'negative', 'indent' => true],
            ['key' => 'packaging', 'label' => 'Packaging', 'kind' => 'negative', 'indent' => true],
            ['key' => 'contribution_margin', 'label' => 'Contribution Margin', 'kind' => 'subtotal'],
            ['key' => 'ad_spend', 'label' => 'Ad Spend', 'kind' => 'negative', 'indent' => true],
            ['key' => 'fixed_opex', 'label' => 'Fixed Opex', 'kind' => 'negative', 'indent' => true],
            ['key' => 'ebitda', 'label' => 'EBITDA', 'kind' => 'total'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return array<string, int|float>
     */
    private function sumColumns(array $columns): array
    {
        $totals = [];

        foreach ($this->rowDefinitions() as $row) {
            $totals[$row['key']] = array_sum(array_column($columns, $row['key']));
        }

        $totals['gross_margin_pct'] = Num::pct($totals['gross_profit'], $totals['net_sales']);
        $totals['contribution_margin_pct'] = Num::pct($totals['contribution_margin'], $totals['net_sales']);
        $totals['ebitda_pct'] = Num::pct($totals['ebitda'], $totals['net_sales']);

        return $totals;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return array<string, mixed>
     */
    private function verdict(array $columns): array
    {
        if ($columns === []) {
            return Verdict::neutral('No months in this window.')->toArray();
        }

        $totals = $this->sumColumns($columns);
        $latest = end($columns);

        if ($totals['ebitda'] < 0) {
            $breakEvenSales = $totals['contribution_margin_pct'] > 0
                ? (int) round(abs($totals['ad_spend'] + $totals['fixed_opex']) / ($totals['contribution_margin_pct'] / 100))
                : 0;

            return Verdict::bad(
                sprintf('EBITDA is negative at %s.', Money::compact((int) $totals['ebitda'])),
                $breakEvenSales > 0
                    ? sprintf('At a %.1f%% contribution margin you need %s of net sales to break even — you did %s.',
                        $totals['contribution_margin_pct'], Money::compact($breakEvenSales), Money::compact((int) $totals['net_sales']))
                    : 'Contribution margin is not positive, so more volume alone will not fix this.',
                'Either lift contribution margin or cut fixed opex — extra revenue at this margin does not close the gap.',
                (int) abs($totals['ebitda']),
            )->toArray();
        }

        return Verdict::good(
            sprintf('EBITDA is positive at %s (%.1f%% of net sales).', Money::compact((int) $totals['ebitda']), $totals['ebitda_pct']),
            sprintf('Latest month closed at %s.', Money::compact((int) $latest['ebitda'])),
        )->toArray();
    }

    /** @return list<array{key: string, label: string, period: Period, opex_share: float}> */
    private function monthsIn(WidgetFilters $filters): array
    {
        $timezone = Tenant::timezone();
        $cursor = $filters->period->from->copy()->startOfMonth();
        $end = $filters->period->to;
        $months = [];

        while ($cursor->lessThanOrEqualTo($end) && count($months) < 24) {
            // A month column must never reach outside the window the user
            // selected, or the statement quietly reports sales they did not ask
            // for. Partial months are clamped and their share recorded so fixed
            // opex can be pro-rated instead of charged in full.
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $from = $monthStart->lessThan($filters->period->from) ? $filters->period->from->copy() : $monthStart;
            $to = $monthEnd->greaterThan($filters->period->to) ? $filters->period->to->copy() : $monthEnd;

            $daysInMonth = (int) $monthStart->daysInMonth;
            $daysCovered = $from->startOfDay()->diffInDays($to->startOfDay()) + 1;

            $months[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $daysCovered < $daysInMonth
                    ? sprintf('%s (%d of %d days)', $cursor->format('M Y'), $daysCovered, $daysInMonth)
                    : $cursor->format('M Y'),
                'period' => new Period($from, $to, $timezone),
                'opex_share' => min(1.0, $daysCovered / $daysInMonth),
            ];

            $cursor = $cursor->copy()->addMonth()->startOfMonth();
        }

        return $months;
    }
}
