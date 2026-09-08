<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Enums\StockMovementType;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * Where the stock went. Every movement in the window, plus the shrinkage those
 * movements add up to — the number nobody tracks until it is large.
 */
class StockLedgerReport extends Report
{
    public function __construct(private readonly DatasetRegistry $datasets) {}

    public function key(): string
    {
        return 'stock_ledger';
    }

    public function label(): string
    {
        return 'Stock Ledger';
    }

    public function category(): string
    {
        return 'Operations & Inventory';
    }

    public function description(): string
    {
        return 'Every stock movement, with the shrinkage it adds up to.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['stock_ledger'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $byType = $this->byType($filters);
        $shrinkage = $this->shrinkage($filters);
        $daily = $this->daily($filters);

        $received = (int) (collect($byType)->firstWhere('type', StockMovementType::PurchaseReceipt->value)['units'] ?? 0);
        $movements = (int) collect($byType)->sum('movements');

        return new ReportPayload(
            kpis: [
                $this->kpi('movements', 'Movements', (float) $movements, null, 'number'),
                $this->kpi('received', 'Units received', (float) $received, null, 'number'),
                $this->kpi('shrinkage_units', 'Units lost to shrinkage', (float) $shrinkage['units'], null, 'number', higherIsBetter: false,
                    tooltip: 'Damage, loss, theft, expiry and negative count corrections.'),
                $this->kpi('shrinkage_value', 'Shrinkage at cost', (float) $shrinkage['value'], null, higherIsBetter: false),
            ],
            sections: [
                Section::table('Where stock moved', $byType, [
                    ['key' => 'label', 'label' => 'Movement', 'format' => 'text'],
                    ['key' => 'movements', 'label' => 'Entries', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'units', 'label' => 'Units', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'value', 'label' => 'Value at cost', 'format' => 'currency', 'align' => 'right'],
                ], 'Positive rows brought stock in; negative rows took it out.'),
                Section::chart(Section::BAR, 'Shrinkage by reason', $shrinkage['by_reason'], 'reason', [
                    ['key' => 'units', 'label' => 'Units', 'format' => 'number'],
                ], $shrinkage['by_reason'] === []
                    ? 'Nothing has been written off, damaged or lost in this window.'
                    : 'The reasons people gave when stock left without being sold.'),
                Section::chart(Section::LINE, 'Movement volume by day', $daily, 'date', [
                    ['key' => 'in', 'label' => 'Units in', 'format' => 'number'],
                    ['key' => 'out', 'label' => 'Units out', 'format' => 'number'],
                ]),
                $this->tableFromDataset($this->datasets->build('stock_ledger', $filters), 'Every movement', exportKey: 'stock_ledger',
                    drilldown: ['dimension' => 'sku', 'value_key' => 'sku_code']),
            ],
            verdict: $this->verdict($shrinkage, $movements),
            caveats: [
                Caveat::note('Only stock this system manages appears here. Levels reported by a sales channel are not movements — they are that channel\'s own count.'),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function byType(WidgetFilters $filters): array
    {
        return DB::table('stock_movements')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('happened_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('type, COUNT(*) AS movements, SUM(quantity) AS units, SUM(quantity * unit_cost) AS value')
            ->groupBy('type')
            ->orderByRaw('ABS(SUM(quantity)) DESC')
            ->get()
            ->map(static fn (object $row): array => [
                'type' => $row->type,
                'label' => StockMovementType::tryFrom((string) $row->type)?->label() ?? $row->type,
                'movements' => (int) $row->movements,
                'units' => (int) $row->units,
                'value' => (int) $row->value,
            ])
            ->all();
    }

    /**
     * Stock that left without being sold. This is the figure that quietly eats
     * margin, so it is named rather than folded into "adjustments".
     *
     * @return array{units: int, value: int, by_reason: list<array<string, mixed>>}
     */
    private function shrinkage(WidgetFilters $filters): array
    {
        $rows = DB::table('stock_movements as m')
            ->join('skus as s', 's.id', '=', 'm.sku_id')
            ->where('m.tenant_id', Tenant::id())
            ->whereBetween('m.happened_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->whereIn('m.type', [
                StockMovementType::Damage->value,
                StockMovementType::WriteOff->value,
                StockMovementType::CountCorrection->value,
                StockMovementType::Adjustment->value,
            ])
            ->where('m.quantity', '<', 0)
            ->selectRaw("COALESCE(NULLIF(m.reason, ''), 'unspecified') AS reason")
            ->selectRaw('SUM(ABS(m.quantity)) AS units, SUM(ABS(m.quantity) * s.cost_price) AS value')
            ->groupByRaw("COALESCE(NULLIF(m.reason, ''), 'unspecified')")
            ->orderByDesc('units')
            ->get()
            ->map(static fn (object $row): array => [
                'reason' => str_replace('_', ' ', (string) $row->reason),
                'units' => (int) $row->units,
                'value' => (int) $row->value,
            ]);

        return [
            'units' => (int) $rows->sum('units'),
            'value' => (int) $rows->sum('value'),
            'by_reason' => $rows->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function daily(WidgetFilters $filters): array
    {
        $rows = DB::table('stock_movements')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('happened_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('DATE(happened_at) AS date')
            ->selectRaw('SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) AS units_in')
            ->selectRaw('SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) AS units_out')
            ->groupBy('date')
            ->get()
            ->keyBy(static fn (object $row): string => (string) $row->date);

        return $filters->period->dateKeys()->map(static fn (string $date): array => [
            'date' => $date,
            'in' => (int) ($rows[$date]->units_in ?? 0),
            'out' => (int) ($rows[$date]->units_out ?? 0),
        ])->all();
    }

    /** @param array{units: int, value: int, by_reason: list<array<string, mixed>>} $shrinkage */
    private function verdict(array $shrinkage, int $movements): Verdict
    {
        if ($movements === 0) {
            return Verdict::neutral('No stock moved in this window', 'Nothing was received, sold, adjusted or written off.');
        }

        if ($shrinkage['units'] === 0) {
            return Verdict::good(
                'No shrinkage recorded',
                sprintf('%s movements, none of them stock lost without a sale.', number_format($movements)),
            );
        }

        $worst = $shrinkage['by_reason'][0] ?? null;

        return Verdict::bad(
            sprintf('%s of stock left without being sold', Money::format($shrinkage['value'])),
            $worst !== null
                ? sprintf('%d units in total, mostly "%s" (%d units).', $shrinkage['units'], $worst['reason'], (int) $worst['units'])
                : sprintf('%d units in total.', $shrinkage['units']),
            'Shrinkage over 1% of stock value usually means a handling or storage problem, not bad luck.',
            $shrinkage['value'],
        );
    }
}
