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
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * The purchase order, written for you: what to buy, how much, and in what
 * order of importance — with the dead stock you should stop buying next to it.
 */
class ReorderReplenishmentReport extends Report
{
    public function __construct(
        private readonly InventoryQuery $inventory,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'reorder_replenishment';
    }

    public function label(): string
    {
        return 'Reorder & Replenishment';
    }

    public function category(): string
    {
        return 'Operations & Inventory';
    }

    public function description(): string
    {
        return 'Days of cover, suggested quantities, ABC class and dead stock.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['reorder'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $reorder = $this->inventory->reorder($filters);
        $rows = collect($reorder['rows'] ?? []);
        $dead = collect($reorder['dead_stock'] ?? []);

        $toOrder = $rows->where('suggested_reorder_qty', '>', 0)->values();
        $orderCost = (int) $toOrder->sum(static fn (array $row): int => (int) $row['suggested_reorder_qty'] * (int) $row['cost_price']);
        $classA = $toOrder->where('abc_class', 'A');

        return new ReportPayload(
            kpis: [
                $this->kpi('to_order', 'SKUs to reorder', (float) $toOrder->count(), null, 'number'),
                $this->kpi('order_cost', 'Cash needed', (float) $orderCost, higherIsBetter: false,
                    tooltip: 'Suggested quantities valued at cost price.'),
                $this->kpi('class_a', 'Class A SKUs to reorder', (float) $classA->count(), null, 'number',
                    tooltip: 'Class A is the top 80% of your revenue — these are the ones that must not go out of stock.'),
                $this->kpi('dead_stock', 'Dead SKUs', (float) $dead->count(), null, 'number', higherIsBetter: false),
            ],
            sections: [
                Section::table('Buy these first', $classA->all(), $this->columns(),
                    'Class A, sorted by revenue. A stockout here costs the most.', exportKey: 'reorder'),
                $this->tableFromDataset($this->datasets->build('reorder', $filters), 'The full replenishment plan', exportKey: 'reorder'),
                Section::chart(Section::BAR, 'Reorder cost by ABC class', $this->byClass($toOrder->all()), 'abc_class', [
                    ['key' => 'order_cost', 'label' => 'Cash needed', 'format' => 'currency'],
                ]),
                Section::table('Stop buying these', $dead->all(), $this->columns(),
                    $dead->isEmpty() ? 'Every SKU sold at least once in the last 30 days.' : 'No sales in 30 days — the cash is stuck here.'),
            ],
            verdict: $this->verdict($toOrder->all(), $classA->all(), $orderCost, $dead->all()),
            caveats: [$this->caveatFrom($reorder['caveat'] ?? null) ?? Caveat::note('Sell-through is measured over the last 30 days.')],
        );
    }

    /** @return list<array<string, mixed>> */
    private function columns(): array
    {
        return [
            ['key' => 'sku_code', 'label' => 'SKU', 'format' => 'text'],
            ['key' => 'name', 'label' => 'Product', 'format' => 'text'],
            ['key' => 'abc_class', 'label' => 'Class', 'format' => 'badge'],
            ['key' => 'stock', 'label' => 'On hand', 'format' => 'number', 'align' => 'right'],
            ['key' => 'daily_rate', 'label' => 'Units / day', 'format' => 'number', 'align' => 'right'],
            ['key' => 'days_of_cover', 'label' => 'Days of cover', 'format' => 'number', 'align' => 'right'],
            ['key' => 'suggested_reorder_qty', 'label' => 'Order qty', 'format' => 'number', 'align' => 'right'],
            ['key' => 'monthly_revenue', 'label' => 'Revenue / month', 'format' => 'currency', 'align' => 'right'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byClass(array $rows): array
    {
        return collect($rows)->groupBy('abc_class')->map(static fn ($group, string $class): array => [
            'abc_class' => 'Class '.$class,
            'order_cost' => (int) $group->sum(static fn (array $row): int => (int) $row['suggested_reorder_qty'] * (int) $row['cost_price']),
            'skus' => $group->count(),
        ])->sortBy('abc_class')->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $toOrder
     * @param  list<array<string, mixed>>  $classA
     * @param  list<array<string, mixed>>  $dead
     */
    private function verdict(array $toOrder, array $classA, int $orderCost, array $dead): Verdict
    {
        if ($toOrder === []) {
            return Verdict::good('Nothing needs reordering', 'Every SKU has enough cover at its current sell-through.');
        }

        $deadValue = (int) collect($dead)->sum('stock_value');

        if ($classA !== []) {
            $revenue = (int) collect($classA)->sum('monthly_revenue');

            return Verdict::bad(
                sprintf('%d class-A SKUs need restocking', count($classA)),
                sprintf('They carry %s of monthly revenue and need %s of stock.', Money::format($revenue), Money::format($orderCost)),
                $deadValue > 0
                    ? sprintf('Fund it by clearing the %s sitting in dead stock.', Money::format($deadValue))
                    : 'Raise the purchase order before cover runs out.',
                $revenue,
            );
        }

        return Verdict::watch(
            sprintf('%d SKUs are due a reorder', count($toOrder)),
            sprintf('%s of stock at cost, none of it class A.', Money::format($orderCost)),
            'Batch these into your next supplier order rather than raising one now.',
        );
    }
}
