<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Operations\Queries\InventoryQuery;
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
 * Stock as a money question: what is sitting in the warehouse, what it is
 * worth, what is about to run out and what will never sell.
 */
class InventoryHealthReport extends Report
{
    public function __construct(
        private readonly InventoryQuery $inventory,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'inventory_health';
    }

    public function label(): string
    {
        return 'Inventory Health';
    }

    public function category(): string
    {
        return 'Operations & Inventory';
    }

    public function description(): string
    {
        return 'Stock levels, turnover and reorder signals by SKU.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['inventory_health', 'reorder'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $coverage = $this->inventory->coverage($filters);
        $critical = $this->inventory->critical($filters);
        $valuation = $this->inventory->valuation($filters);
        $reorder = $this->inventory->reorder($filters);

        $dead = collect($reorder['dead_stock'] ?? []);
        $stockValue = (int) $coverage->sum('stock_value');
        $deadValue = (int) $dead->sum('stock_value');

        $classes = collect($reorder['rows'] ?? [])->groupBy('abc_class')->map(static fn ($group, string $class): array => [
            'abc_class' => $class,
            'skus' => $group->count(),
            'stock_value' => (int) $group->sum('stock_value'),
            'monthly_revenue' => (int) $group->sum('monthly_revenue'),
        ])->sortBy('abc_class')->values()->all();

        return new ReportPayload(
            kpis: [
                $this->kpi('stock_value', 'Stock at cost', (float) $stockValue,
                    tooltip: 'Units on hand valued at their cost price — money sitting on a shelf.'),
                $this->kpi('skus_in_stock', 'SKUs in stock', (float) $coverage->where('stock', '>', 0)->count(), null, 'number'),
                $this->kpi('critical', 'Below cover threshold', (float) $critical['count'], null, 'number', higherIsBetter: false,
                    tooltip: sprintf('SKUs with under %d days of cover.', (int) $critical['threshold_days'])),
                $this->kpi('dead_stock', 'Dead stock value', (float) $deadValue, higherIsBetter: false,
                    tooltip: 'Stock with no sales at all in the last 30 days.'),
            ],
            sections: [
                Section::table('Running out first', collect($critical['rows'])->all(), $this->coverageColumns(),
                    sprintf('%d SKUs are under %d days of cover, carrying %s of monthly revenue.',
                        (int) $critical['count'], (int) $critical['threshold_days'], Money::format((int) $critical['revenue_at_risk'])),
                    exportKey: 'reorder'),
                Section::callouts('ABC classes', array_map(static fn (array $row): array => [
                    'label' => 'Class '.$row['abc_class'],
                    'value' => $row['stock_value'],
                    'format' => 'currency',
                    'note' => sprintf('%d SKUs · %s a month', (int) $row['skus'], Money::format((int) $row['monthly_revenue'])),
                ], $classes), 'A is your top 80% of revenue, B the next 15%, C the tail.'),
                $this->tableFromDataset($this->datasets->build('inventory_health', $filters), 'Every SKU', exportKey: 'inventory_health'),
                Section::table('Dead stock', $dead->all(), $this->coverageColumns(),
                    $dead->isEmpty() ? 'Nothing is sitting completely still.' : sprintf('%s of cost tied up in stock that did not move at all.', Money::format($deadValue))),
            ],
            verdict: $this->verdict($critical, $deadValue, $stockValue, $valuation),
            caveats: [
                Caveat::note($reorder['caveat'] ?? 'Days of cover uses the last 30 days of sell-through.'),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function coverageColumns(): array
    {
        return [
            ['key' => 'sku_code', 'label' => 'SKU', 'format' => 'text'],
            ['key' => 'name', 'label' => 'Product', 'format' => 'text'],
            ['key' => 'stock', 'label' => 'On hand', 'format' => 'number', 'align' => 'right'],
            ['key' => 'units_30d', 'label' => 'Sold (30d)', 'format' => 'number', 'align' => 'right'],
            ['key' => 'days_of_cover', 'label' => 'Days of cover', 'format' => 'number', 'align' => 'right'],
            ['key' => 'stock_value', 'label' => 'Stock at cost', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'monthly_revenue', 'label' => 'Revenue / month', 'format' => 'currency', 'align' => 'right'],
            ['key' => 'suggested_reorder_qty', 'label' => 'Suggested reorder', 'format' => 'number', 'align' => 'right'],
        ];
    }

    /**
     * @param  array<string, mixed>  $critical
     * @param  array<string, mixed>  $valuation
     */
    private function verdict(array $critical, int $deadValue, int $stockValue, array $valuation): Verdict
    {
        if ((int) $critical['count'] > 0) {
            return Verdict::bad(
                sprintf('%d SKUs run out inside %d days', (int) $critical['count'], (int) $critical['threshold_days']),
                sprintf('%s of monthly revenue depends on them.', Money::format((int) $critical['revenue_at_risk'])),
                'Raise purchase orders for the top of that list today — a stockout on an A-class SKU is the most expensive kind.',
                (int) $critical['revenue_at_risk'],
            );
        }

        $deadShare = Num::pct($deadValue, $stockValue);

        if ($deadShare > 25) {
            return Verdict::watch(
                sprintf('%.0f%% of your stock value has not moved in 30 days', $deadShare),
                sprintf('%s of cost is tied up in inventory nobody is buying.', Money::format($deadValue)),
                'Bundle it, discount it, or stop reordering it — that cash is doing nothing.',
            );
        }

        return Verdict::good(
            'Stock cover is healthy',
            sprintf('%s of inventory at cost, nothing under the cover threshold.', Money::format($stockValue)),
        );
    }
}
