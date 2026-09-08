<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Inventory\Services\BatchLedger;
use App\Domain\Inventory\Services\StockLedger;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Models\StockBatch;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * What the stock on the shelf is worth — at what it cost, at what it would
 * fetch, and how much of it is aging toward being worth nothing.
 */
class InventoryValuationReport extends Report
{
    public function __construct(
        private readonly DatasetRegistry $datasets,
        private readonly BatchLedger $batches,
    ) {}

    public function key(): string
    {
        return 'inventory_valuation';
    }

    public function label(): string
    {
        return 'Inventory Valuation';
    }

    public function category(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Stock value at cost, at retail, and what is aging out.';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['inventory_valuation'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $totals = $this->totals();
        $byCategory = $this->byCategory();
        $expiring = $this->expiryBuckets();
        $batchValue = $this->batches->valuation();
        $coverage = $this->batchCoverage($totals['units']);

        return new ReportPayload(
            kpis: [
                $this->kpi('at_cost', 'Value at cost', (float) $totals['at_cost'],
                    tooltip: 'Units on hand at the SKU\'s current cost price.'),
                $this->kpi('at_retail', 'Value at retail', (float) $totals['at_retail'],
                    tooltip: 'What the same units would bring in at full selling price.'),
                $this->kpi('units', 'Units held', (float) $totals['units'], null, 'number'),
                $this->kpi('margin_locked', 'Margin locked in stock',
                    Num::pct($totals['at_retail'] - $totals['at_cost'], $totals['at_retail']), null, 'percent',
                    tooltip: 'The gross margin sitting on the shelf, before any discount.'),
            ],
            sections: [
                Section::callouts('Two ways of valuing the same shelf', [
                    ['label' => 'At current cost price', 'value' => $totals['at_cost'], 'format' => 'currency',
                        'note' => sprintf('All %s units × what each SKU costs today.', number_format($totals['units']))],
                    ['label' => 'At batch cost (FIFO)', 'value' => $batchValue, 'format' => 'currency',
                        'note' => $coverage['units'] === 0
                            ? 'Nothing is batch-tracked yet, so there is no FIFO figure to give.'
                            : sprintf('Covers the %s batch-tracked units only — %.0f%% of what you hold.',
                                number_format($coverage['units']), $coverage['pct'])],
                    // Comparing a partial FIFO figure with a full average would
                    // read as a huge loss, so the two are only set against each
                    // other on the stock both actually cover.
                    ['label' => $coverage['pct'] >= 99 ? 'FIFO vs average' : 'FIFO vs average, on batch-tracked stock',
                        'value' => $coverage['units'] === 0 ? 0 : $batchValue - $coverage['at_average'],
                        'format' => 'currency',
                        'tone' => $coverage['units'] > 0 && abs($batchValue - $coverage['at_average']) > max(1, $coverage['at_average']) * 0.05 ? 'watch' : 'good',
                        'note' => $coverage['units'] === 0
                            ? 'Turn on batch tracking to compare.'
                            : 'A gap here means cost prices moved after those units were bought.'],
                    ['label' => 'At selling price', 'value' => $totals['at_retail'], 'format' => 'currency'],
                ]),
                Section::chart(Section::BAR, 'Stock value by category', $byCategory, 'category', [
                    ['key' => 'at_cost', 'label' => 'At cost', 'format' => 'currency'],
                    ['key' => 'at_retail', 'label' => 'At retail', 'format' => 'currency'],
                ]),
                Section::table('Batches by time left', $expiring, [
                    ['key' => 'bucket', 'label' => 'Expires in', 'format' => 'text'],
                    ['key' => 'batches', 'label' => 'Batches', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'units', 'label' => 'Units', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'value', 'label' => 'Value at cost', 'format' => 'currency', 'align' => 'right'],
                ], $expiring === [] ? 'No batch-tracked stock carries an expiry date.' : 'Stock closest to expiry first.'),
                $this->tableFromDataset($this->datasets->build('inventory_valuation', $filters), 'Every SKU',
                    exportKey: 'inventory_valuation', drilldown: ['dimension' => 'sku', 'value_key' => 'sku_code']),
            ],
            verdict: $this->verdict($totals, $expiring),
            caveats: [
                Caveat::note('Valuation is a point-in-time figure, so the date range does not apply to it.'),
                match (true) {
                    $coverage['units'] === 0 => Caveat::partial('No stock is batch-tracked yet, so the FIFO column is empty rather than estimated. Turn on batch tracking on a SKU to populate it.'),
                    $coverage['pct'] < 99 => Caveat::partial(sprintf(
                        'Only %.0f%% of your units are batch-tracked, so the FIFO figure covers that slice alone. It is not comparable with the full at-cost number above.',
                        $coverage['pct'],
                    )),
                    default => null,
                },
            ],
        );
    }

    /** @return array{at_cost: int, at_retail: int, units: int} */
    private function totals(): array
    {
        $row = DB::table('skus as s')
            ->join('inventory as i', function ($join): void {
                $join->on('i.sku_id', '=', 's.id')->where('i.source', StockLedger::SOURCE);
            })
            ->where('s.tenant_id', Tenant::id())
            ->where('s.is_active', true)
            ->where('i.on_hand', '>', 0)
            ->selectRaw('COALESCE(SUM(i.on_hand), 0) AS units')
            ->selectRaw('COALESCE(SUM(i.on_hand * s.cost_price), 0) AS at_cost')
            ->selectRaw('COALESCE(SUM(i.on_hand * s.selling_price), 0) AS at_retail')
            ->first();

        return [
            'units' => (int) ($row->units ?? 0),
            'at_cost' => (int) ($row->at_cost ?? 0),
            'at_retail' => (int) ($row->at_retail ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function byCategory(): array
    {
        return DB::table('skus as s')
            ->join('inventory as i', function ($join): void {
                $join->on('i.sku_id', '=', 's.id')->where('i.source', StockLedger::SOURCE);
            })
            ->where('s.tenant_id', Tenant::id())
            ->where('i.on_hand', '>', 0)
            ->selectRaw("COALESCE(NULLIF(s.category, ''), 'Uncategorised') AS category")
            ->selectRaw('SUM(i.on_hand) AS units')
            ->selectRaw('SUM(i.on_hand * s.cost_price) AS at_cost')
            ->selectRaw('SUM(i.on_hand * s.selling_price) AS at_retail')
            ->groupByRaw("COALESCE(NULLIF(s.category, ''), 'Uncategorised')")
            ->orderByDesc('at_cost')
            ->limit(20)
            ->get()
            ->map(static fn (object $row): array => [
                'category' => $row->category,
                'units' => (int) $row->units,
                'at_cost' => (int) $row->at_cost,
                'at_retail' => (int) $row->at_retail,
            ])
            ->all();
    }

    /**
     * How much of the shelf is batch-tracked, and what that same slice is worth
     * at average cost — the only fair thing to compare a FIFO figure against.
     *
     * @return array{units: int, pct: float, at_average: int}
     */
    private function batchCoverage(int $totalUnits): array
    {
        $row = DB::table('stock_batches as b')
            ->join('skus as s', 's.id', '=', 'b.sku_id')
            ->where('b.tenant_id', Tenant::id())
            ->where('b.quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(b.quantity), 0) AS units')
            ->selectRaw('COALESCE(SUM(b.quantity * s.cost_price), 0) AS at_average')
            ->first();

        $units = (int) ($row->units ?? 0);

        return [
            'units' => $units,
            'pct' => Num::pct($units, $totalUnits),
            'at_average' => (int) ($row->at_average ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function expiryBuckets(): array
    {
        $batches = StockBatch::query()
            ->where('quantity', '>', 0)
            ->whereNotNull('expires_on')
            ->get();

        if ($batches->isEmpty()) {
            return [];
        }

        $buckets = ['Already expired' => [], 'Within 30 days' => [], '31-90 days' => [], '91-180 days' => [], 'Over 180 days' => []];

        foreach ($batches as $batch) {
            $days = $batch->daysToExpiry() ?? 999;

            $key = match (true) {
                $days < 0 => 'Already expired',
                $days <= 30 => 'Within 30 days',
                $days <= 90 => '31-90 days',
                $days <= 180 => '91-180 days',
                default => 'Over 180 days',
            };

            $buckets[$key][] = $batch;
        }

        $rows = [];

        foreach ($buckets as $label => $group) {
            if ($group === []) {
                continue;
            }

            $rows[] = [
                'bucket' => $label,
                'batches' => count($group),
                'units' => array_sum(array_map(static fn (StockBatch $b): int => $b->quantity, $group)),
                'value' => array_sum(array_map(static fn (StockBatch $b): int => $b->quantity * $b->unit_cost, $group)),
            ];
        }

        return $rows;
    }

    /**
     * @param  array{at_cost: int, at_retail: int, units: int}  $totals
     * @param  list<array<string, mixed>>  $expiring
     */
    private function verdict(array $totals, array $expiring): Verdict
    {
        if ($totals['units'] === 0) {
            return Verdict::neutral('No stock on hand', 'Nothing is being held in the locations this system manages.');
        }

        $expired = collect($expiring)->firstWhere('bucket', 'Already expired');

        if ($expired !== null) {
            return Verdict::bad(
                sprintf('%s of stock has already expired', Money::format((int) $expired['value'])),
                sprintf('%d units across %d batches cannot be sold.', (int) $expired['units'], (int) $expired['batches']),
                'Write them off — until you do, your stock value counts goods you cannot ship.',
                (int) $expired['value'],
            );
        }

        $soon = collect($expiring)->firstWhere('bucket', 'Within 30 days');

        if ($soon !== null) {
            return Verdict::watch(
                sprintf('%s expires within 30 days', Money::format((int) $soon['value'])),
                sprintf('%d units are on a clock.', (int) $soon['units']),
                'Discount or bundle them while they can still be sold at something.',
            );
        }

        return Verdict::good(
            sprintf('%s of stock at cost', Money::format($totals['at_cost'])),
            sprintf('%s at retail — %.0f%% gross margin sitting on the shelf.',
                Money::format($totals['at_retail']),
                Num::pct($totals['at_retail'] - $totals['at_cost'], $totals['at_retail'])),
        );
    }
}
