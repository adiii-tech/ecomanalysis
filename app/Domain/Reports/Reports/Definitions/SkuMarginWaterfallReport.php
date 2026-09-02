<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Support\Caveat;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * MRP → discount → net → COGS → fees → margin, one SKU at a time. The report
 * that tells a brand which product is quietly subsidising the rest.
 */
class SkuMarginWaterfallReport extends Report
{
    public function __construct(private readonly DatasetRegistry $datasets) {}

    public function key(): string
    {
        return 'sku_margin_waterfall';
    }

    public function label(): string
    {
        return 'SKU Margin Waterfall';
    }

    public function category(): string
    {
        return 'Profit & Margin';
    }

    public function description(): string
    {
        return 'MRP to margin, step by step, per SKU.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['sku_margin_waterfall', 'top_skus'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $dataset = $this->datasets->build('sku_margin_waterfall', $filters);
        $rows = $dataset->rows->map(static fn (array|object $row): array => (array) $row)->values()->all();

        $negative = array_values(array_filter($rows, static fn (array $row): bool => (float) ($row['margin_pct'] ?? 0) <= 0));
        $best = $rows === [] ? null : collect($rows)->sortByDesc('margin')->first();
        $worst = $rows === [] ? null : collect($rows)->sortBy('margin')->first();

        $totalMargin = $this->sumRows($rows, 'margin');
        $totalNet = $this->sumRows($rows, 'net_sales');

        return new ReportPayload(
            kpis: [
                $this->kpi('skus', 'SKUs sold', (float) count($rows), null, 'number'),
                $this->kpi('margin', 'Contribution from SKUs', (float) $totalMargin),
                $this->kpi('margin_pct', 'Blended SKU margin', Num::pct($totalMargin, $totalNet), null, 'percent'),
                $this->kpi('negative', 'SKUs at or below zero margin', (float) count($negative), null, 'number', higherIsBetter: false),
            ],
            sections: [
                $best !== null
                    ? Section::waterfall(
                        sprintf('How %s earns its margin', $best['name'] ?? $best['sku_code'] ?? 'your best SKU'),
                        $this->waterfallFor($best),
                        'Your highest-contributing SKU, step by step.',
                    )
                    : Section::narrative('No SKU data', ['Nothing sold in this window.']),
                $worst !== null && $worst !== $best
                    ? Section::waterfall(
                        sprintf('And where %s loses it', $worst['name'] ?? $worst['sku_code'] ?? 'your worst SKU'),
                        $this->waterfallFor($worst),
                        'The same chain for your weakest SKU.',
                    )
                    : null,
                $this->tableFromDataset($dataset, 'Every SKU, MRP to margin', exportKey: 'sku_margin_waterfall',
                    drilldown: ['dimension' => 'sku', 'value_key' => 'sku_code']),
                Section::chart(Section::BAR, 'Margin % by SKU', array_slice($rows, 0, 20), 'sku_code', [
                    ['key' => 'margin_pct', 'label' => 'Margin %', 'format' => 'percent'],
                ]),
            ],
            verdict: $this->verdict($negative, $worst, $totalMargin),
            caveats: [
                Caveat::note('Fees and logistics are allocated to a SKU by its share of net sales in the window, because marketplaces settle at order level, not line level.'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function waterfallFor(array $row): array
    {
        $steps = [
            ['label' => 'Gross', 'value' => (int) ($row['gross_sales'] ?? 0), 'type' => 'delta'],
            ['label' => 'Discounts', 'value' => -(int) ($row['discounts'] ?? 0), 'type' => 'delta'],
            ['label' => 'Returns', 'value' => -(int) ($row['returned_amount'] ?? 0), 'type' => 'delta'],
            ['label' => 'COGS', 'value' => -(int) ($row['cogs'] ?? 0), 'type' => 'delta'],
            ['label' => 'Fees', 'value' => -(int) ($row['fees'] ?? 0), 'type' => 'delta'],
            ['label' => 'Logistics & other', 'value' => -(int) ($row['other_costs'] ?? 0), 'type' => 'delta'],
        ];

        $running = 0;
        $series = [];

        foreach ($steps as $step) {
            $start = $running;
            $running += $step['value'];
            $series[] = [...$step, 'start' => $start, 'end' => $running];
        }

        $series[] = ['label' => 'Contribution', 'value' => $running, 'type' => 'total', 'start' => 0, 'end' => $running];

        return $series;
    }

    /**
     * @param  list<array<string, mixed>>  $negative
     * @param  array<string, mixed>|null  $worst
     */
    private function verdict(array $negative, ?array $worst, int $totalMargin): Verdict
    {
        if ($worst === null) {
            return Verdict::neutral('No SKUs sold', 'Nothing to break down in this window.');
        }

        if ($negative !== []) {
            $drag = abs((int) collect($negative)->sum('margin'));

            return Verdict::bad(
                sprintf('%d SKUs are sold at a loss', count($negative)),
                sprintf('%s is the worst at %s contribution. Together they drag %s off your margin.',
                    $worst['name'] ?? $worst['sku_code'] ?? 'One SKU', Money::format((int) $worst['margin']), Money::format($drag)),
                'Reprice, renegotiate the cost, or stop promoting them — do not scale a loss.',
                $drag,
            );
        }

        return Verdict::good(
            'Every SKU is margin-positive',
            sprintf('Total SKU contribution is %s in this window.', Money::format($totalMargin)),
            'Push the top of the table harder — that is where extra volume pays best.',
        );
    }
}
