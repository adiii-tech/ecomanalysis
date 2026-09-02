<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * A working view for GST reconciliation: output tax by HSN and rate, with the
 * B2C/B2B and intra/inter-state split your filing needs. It is explicitly not
 * a return — it is the sheet you hand your CA.
 */
class GstSummaryReport extends Report
{
    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'gst_summary';
    }

    public function label(): string
    {
        return 'GST Summary';
    }

    public function category(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Output tax, HSN-wise, B2C/B2B split.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['gst_summary'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $dataset = $this->datasets->build('gst_summary', $filters);
        $rows = $dataset->rows->map(static fn (array|object $row): array => (array) $row)->values()->all();

        $totals = $this->rollups->totals($filters);
        $previous = $this->rollups->totals($filters->previous());

        $tax = $this->sumRows($rows, 'tax_amount');
        $taxable = $this->sumRows($rows, 'taxable_value');
        $unclassified = array_values(array_filter($rows, static fn (array $row): bool => $row['hsn'] === 'Unclassified'));

        $byRate = collect($rows)->groupBy('gst_rate')->map(static fn ($group, string $rate): array => [
            'rate' => $rate.'%',
            'taxable_value' => (int) $group->sum('taxable_value'),
            'tax_amount' => (int) $group->sum('tax_amount'),
            'orders' => (int) $group->sum('orders'),
        ])->sortByDesc('taxable_value')->values()->all();

        $homeState = Tenant::current()->gst_state;
        $placeOfSupply = $this->placeOfSupply($filters);

        return new ReportPayload(
            kpis: [
                $this->kpi('taxable_value', 'Taxable value', (float) $taxable),
                $this->kpi('output_tax', 'Output tax', (float) $tax, (float) $previous['tax_amount'], higherIsBetter: false),
                $this->kpi('effective_rate', 'Effective rate', Num::pct($tax, $taxable), null, 'percent',
                    tooltip: 'Total tax over total taxable value — the blended rate across your catalogue.'),
                $this->kpi('unclassified', 'SKUs without an HSN', (float) count($unclassified), null, 'number', higherIsBetter: false),
            ],
            sections: [
                $this->tableFromDataset($dataset, 'HSN-wise summary', exportKey: 'gst_summary'),
                Section::table('By rate slab', $byRate, [
                    ['key' => 'rate', 'label' => 'Rate', 'format' => 'text'],
                    ['key' => 'orders', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'taxable_value', 'label' => 'Taxable value', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'tax_amount', 'label' => 'Tax', 'format' => 'currency', 'align' => 'right'],
                ]),
                Section::table('Place of supply', $placeOfSupply, [
                    ['key' => 'state', 'label' => 'State', 'format' => 'text'],
                    ['key' => 'supply_type', 'label' => 'Supply', 'format' => 'badge'],
                    ['key' => 'orders', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'taxable_value', 'label' => 'Taxable value', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'tax_amount', 'label' => 'Tax', 'format' => 'currency', 'align' => 'right'],
                ], $homeState === null
                    ? 'Set your GST state in Admin → Settings and this table will split intra-state supply from inter-state.'
                    : sprintf('Supplying from %s. Intra-state splits CGST and SGST; inter-state is IGST.', $homeState)),
            ],
            verdict: $this->verdict($unclassified, $tax, $taxable),
            caveats: [
                $this->caveatFrom($dataset->caveat),
                Caveat::partial('Nothing here is a filed return. CGST and SGST are split evenly on intra-state supply and no input credit is applied — hand this to your CA as a reconciliation sheet, not as GSTR-1.'),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function placeOfSupply(WidgetFilters $filters): array
    {
        $homeState = Tenant::current()->gst_state;

        $rows = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.tenant_id', Tenant::id())
            ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->where('o.status', '!=', 'cancelled')
            ->whereNotNull('o.shipping_state')
            ->selectRaw('o.shipping_state AS state, COUNT(DISTINCT o.id) AS orders')
            ->selectRaw('COALESCE(SUM(oi.line_net),0) - COALESCE(SUM(oi.tax),0) AS taxable_value')
            ->selectRaw('COALESCE(SUM(oi.tax),0) AS tax_amount')
            ->groupBy('o.shipping_state')
            ->orderByDesc('taxable_value')
            ->get();

        return $rows->map(static fn (object $row): array => [
            'state' => $row->state,
            'supply_type' => match (true) {
                $homeState === null => 'Set your state',
                strcasecmp((string) $row->state, $homeState) === 0 => 'Intra-state',
                default => 'Inter-state',
            },
            'orders' => (int) $row->orders,
            'taxable_value' => (int) $row->taxable_value,
            'tax_amount' => (int) $row->tax_amount,
        ])->all();
    }

    /** @param list<array<string, mixed>> $unclassified */
    private function verdict(array $unclassified, int $tax, int $taxable): Verdict
    {
        if ($taxable === 0) {
            return Verdict::neutral('Nothing to report', 'No taxable supply in this window.');
        }

        if ($unclassified !== []) {
            $value = (int) collect($unclassified)->sum('taxable_value');

            return Verdict::bad(
                'Some sales have no HSN code',
                sprintf('%s of taxable value sits under "Unclassified" and cannot be filed as-is.', Money::format($value)),
                'Add HSN codes to those SKUs in your catalogue before the next filing date.',
                $value,
            );
        }

        return Verdict::good(
            sprintf('%s of output tax, fully classified', Money::format($tax)),
            sprintf('Blended rate of %.2f%% across %s of taxable value.', Num::pct($tax, $taxable), Money::format($taxable)),
            'Reconcile against your GSTR-1 before filing.',
        );
    }
}
