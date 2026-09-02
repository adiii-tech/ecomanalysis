<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Operations\Queries\InventoryQuery;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Support\Money;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * The revenue you never got to record. Estimated from each SKU's own recent
 * sell-through and the days it spent at zero stock — grounded in your data,
 * and labelled as an estimate everywhere it appears.
 */
class StockoutReport extends Report
{
    public function __construct(
        private readonly InventoryQuery $inventory,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'stockout';
    }

    public function label(): string
    {
        return 'Stockout Impact';
    }

    public function category(): string
    {
        return 'Operations & Inventory';
    }

    public function description(): string
    {
        return 'Revenue lost to out-of-stock per SKU.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['stockout', 'zero_order_skus'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $stockouts = $this->inventory->stockouts($filters);
        $rows = collect($stockouts['rows']);
        $lost = (int) $stockouts['total_lost_revenue'];

        $coverage = $this->inventory->coverage($filters);
        $atZero = $coverage->where('stock', '<=', 0);

        return new ReportPayload(
            kpis: [
                $this->kpi('lost_revenue', 'Estimated revenue lost', (float) $lost, higherIsBetter: false),
                $this->kpi('skus_out', 'SKUs at zero stock', (float) $atZero->count(), null, 'number', higherIsBetter: false),
                $this->kpi('selling_skus_out', 'Of those, still in demand', (float) $rows->count(), null, 'number', higherIsBetter: false,
                    tooltip: 'SKUs that were selling before they ran out — the ones that actually cost you money.'),
                $this->kpi('avg_days_out', 'Average days out of stock',
                    (float) round($rows->avg('days_out_of_stock') ?? 0, 1), null, 'days', higherIsBetter: false),
            ],
            sections: [
                $this->tableFromDataset($this->datasets->build('stockout', $filters), 'What each stockout cost', exportKey: 'stockout'),
                Section::chart(Section::BAR, 'Biggest losses', $rows->take(15)->map(static fn (array $row): array => [
                    'sku_code' => $row['sku_code'],
                    'estimated_lost_revenue' => $row['estimated_lost_revenue'],
                ])->values()->all(), 'sku_code', [
                    ['key' => 'estimated_lost_revenue', 'label' => 'Estimated lost revenue', 'format' => 'currency'],
                ]),
                $this->tableFromDataset($this->datasets->build('zero_order_skus', $filters), 'Listed but never ordered', exportKey: 'zero_order_skus',
                    subtitle: 'The opposite problem: SKUs sitting in the catalogue that nobody buys.'),
            ],
            verdict: $this->verdict($rows->all(), $lost),
            caveats: [$this->caveatFrom($stockouts['caveat'] ?? null)],
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function verdict(array $rows, int $lost): Verdict
    {
        if ($rows === []) {
            return Verdict::good('Nothing that sells is out of stock', 'No SKU with recent demand hit zero in this window.');
        }

        $worst = $rows[0];

        return Verdict::bad(
            sprintf('Stockouts cost you roughly %s', Money::format($lost)),
            sprintf('%s alone was unavailable for %d days at %s of demand.',
                $worst['name'] ?? $worst['sku_code'], (int) $worst['days_out_of_stock'], Money::format((int) $worst['estimated_lost_revenue'])),
            'Set a reorder point on these SKUs — this is revenue you had already earned the demand for.',
            $lost,
        );
    }
}
