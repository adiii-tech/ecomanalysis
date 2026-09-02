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
 * Did the promo buy volume or burn margin? Every code is judged against the
 * AOV of undiscounted orders in the same window, so "it drove sales" has to
 * survive a comparison.
 */
class DiscountImpactReport extends Report
{
    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'discount_impact';
    }

    public function label(): string
    {
        return 'Discount Impact';
    }

    public function category(): string
    {
        return 'Profit & Margin';
    }

    public function description(): string
    {
        return 'Did the promo buy volume or burn margin?';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['discount_impact'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $dataset = $this->datasets->build('discount_impact', $filters);
        $rows = $dataset->rows->map(static fn (array|object $row): array => (array) $row)->values()->all();

        $totals = $this->rollups->totals($filters);
        $previous = $this->rollups->totals($filters->previous());

        $burned = array_values(array_filter($rows, static fn (array $row): bool => $row['verdict'] === 'Burned margin'));
        $worked = array_values(array_filter($rows, static fn (array $row): bool => $row['verdict'] === 'Bought volume'));
        $burnedAmount = $this->sumRows($burned, 'discount');

        return new ReportPayload(
            kpis: [
                $this->kpi('discounts', 'Discounts Given', (float) $totals['discounts'], (float) $previous['discounts'], higherIsBetter: false),
                $this->kpi('discount_depth', 'Discount Depth', Num::pct($totals['discounts'], $totals['gross_sales']),
                    Num::pct($previous['discounts'], $previous['gross_sales']), 'percent', higherIsBetter: false,
                    tooltip: 'Discounts as a share of gross sales.'),
                $this->kpi('codes', 'Codes Used', (float) count($rows), null, 'number'),
                $this->kpi('burned', 'Given away with no return', (float) $burnedAmount, null, higherIsBetter: false,
                    tooltip: 'Discount spend on codes whose orders came in at or below zero contribution margin.'),
            ],
            sections: [
                $this->tableFromDataset($dataset, 'Every code in this window', exportKey: 'discount_impact',
                    drilldown: ['dimension' => 'discount_code', 'value_key' => 'code']),
                Section::chart(Section::BAR, 'Discount given vs margin earned', array_slice($rows, 0, 15), 'code', [
                    ['key' => 'discount', 'label' => 'Discount given', 'format' => 'currency'],
                    ['key' => 'margin', 'label' => 'Contribution margin', 'format' => 'currency'],
                ], 'A code whose discount bar is taller than its margin bar cost more than it earned.'),
                Section::callouts('The scoreboard', [
                    ['label' => 'Codes that bought volume', 'value' => count($worked), 'format' => 'number', 'tone' => 'good',
                        'note' => 'AOV above the undiscounted baseline and still margin-positive.'],
                    ['label' => 'Codes that burned margin', 'value' => count($burned), 'format' => 'number', 'tone' => 'bad',
                        'note' => 'Orders landed at zero or negative contribution.'],
                    ['label' => 'Spend on burning codes', 'value' => $burnedAmount, 'format' => 'currency', 'tone' => 'bad'],
                ]),
            ],
            verdict: $this->verdict($rows, $burned, $burnedAmount, $totals),
            caveats: array_filter([
                $rows === [] ? Caveat::partial('No order in this window carried a discount code.') : null,
                Caveat::note('Codes are read from the order as the store recorded them; a stacked or automatic discount with no code shows up in total discounts but not as a row here.'),
            ]),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $burned
     * @param  array<string, int>  $totals
     */
    private function verdict(array $rows, array $burned, int $burnedAmount, array $totals): Verdict
    {
        if ($rows === []) {
            return Verdict::neutral('No discount codes used', 'Nothing to judge in this window.');
        }

        if ($burned !== []) {
            $worst = $burned[0];

            return Verdict::bad(
                sprintf('%s is giving away margin', $worst['code']),
                sprintf('%s in discounts across %d orders returned %s in contribution.',
                    Money::format((int) $worst['discount']), (int) $worst['orders'], Money::format((int) $worst['margin'])),
                'Retire or tighten the code — cap it by cart value or exclude the low-margin SKUs.',
                $burnedAmount,
            );
        }

        $depth = Num::pct($totals['discounts'], $totals['gross_sales']);

        if ($depth > 20) {
            return Verdict::watch(
                sprintf('Discounting is deep at %.1f%% of gross', $depth),
                'Every code is margin-positive, but the whole business is running on promotion.',
                'Test one week at a lower depth on your best-selling SKU before the next campaign.',
            );
        }

        return Verdict::good(
            'Discounts are pulling their weight',
            sprintf('%.1f%% of gross went to discounts and every code stayed margin-positive.', $depth),
            'Scale the code with the highest AOV lift.',
        );
    }
}
