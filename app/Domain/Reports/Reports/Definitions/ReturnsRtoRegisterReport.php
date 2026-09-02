<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Operations\Queries\ReturnsQuery;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Models\Benchmark;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * The line-item register, in a column order that pastes straight into Tally,
 * plus the reasons behind it. Reads on either basis: the return date, or the
 * date of the order the return belongs to.
 */
class ReturnsRtoRegisterReport extends Report
{
    public function __construct(
        private readonly ReturnsQuery $returns,
        private readonly RollupQuery $rollups,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'returns_rto_register';
    }

    public function label(): string
    {
        return 'Returns & RTO Register';
    }

    public function category(): string
    {
        return 'Returns & Cash';
    }

    public function description(): string
    {
        return 'Line-item register in Tally-matching column format.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['returns_register'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $threshold = (float) Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()])->return_threshold_pct;

        $kpis = $this->returns->kpis($filters, $threshold);
        $totals = $this->rollups->totals($filters);
        $previous = $this->rollups->totals($filters->previous());

        $byReason = $this->returns->byReason($filters, 15);
        $byChannel = $this->returns->byChannel($filters);
        $topSkus = $this->returns->topReturnSkus($filters, 15);
        $trend = $this->returns->trend($filters);

        return new ReportPayload(
            kpis: [
                $this->kpi('returned_amount', 'Returned value', (float) $totals['returned_amount'], (float) $previous['returned_amount'], higherIsBetter: false),
                $this->kpi('rto_amount', 'RTO value', (float) $totals['rto_amount'], (float) $previous['rto_amount'], higherIsBetter: false),
                $this->kpi('return_rate', 'Return rate', Num::pct($totals['returned_amount'], $totals['invoiced_sales']),
                    Num::pct($previous['returned_amount'], $previous['invoiced_sales']), 'percent', higherIsBetter: false),
                $this->kpi('return_cost', 'Reverse logistics cost', (float) $totals['return_cost'], (float) $previous['return_cost'], higherIsBetter: false,
                    tooltip: 'Handling and freight on returns and RTO, from your cost settings.'),
            ],
            sections: [
                $this->tableFromDataset($this->datasets->build('returns_register', $filters),
                    'The register', exportKey: 'returns_register',
                    subtitle: $filters->usesReturnDateBasis()
                        ? 'Read on return date: every return that happened in this window, whenever the order was placed.'
                        : 'Read on order date: every return belonging to orders placed in this window, whenever it came back.'),
                Section::chart(Section::BAR, 'Why things come back', $byReason->all(), 'label', [
                    ['key' => 'count', 'label' => 'Returns', 'format' => 'number'],
                    ['key' => 'refund_amount', 'label' => 'Refunded', 'format' => 'currency'],
                ]),
                Section::table('Worst SKUs', $topSkus->all(), [
                    ['key' => 'sku_code', 'label' => 'SKU', 'format' => 'text'],
                    ['key' => 'name', 'label' => 'Product', 'format' => 'text'],
                    ['key' => 'returns_count', 'label' => 'Returns', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'units', 'label' => 'Units returned', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'refund_amount', 'label' => 'Refunded', 'format' => 'currency', 'align' => 'right'],
                ], 'A single SKU with a high return rate is usually a sizing or photography problem, not a customer problem.'),
                Section::chart(Section::LINE, 'Returns over time', $trend['series'], 'date', [
                    ['key' => 'customer_returns', 'label' => 'Customer returns', 'format' => 'number'],
                    ['key' => 'rto_events', 'label' => 'RTO', 'format' => 'number'],
                ]),
                Section::table('By channel', $byChannel['rows'], [
                    ['key' => 'name', 'label' => 'Channel', 'format' => 'text'],
                    ['key' => 'orders', 'label' => 'Invoiced orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'customer_returns', 'label' => 'Customer returns', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'rto_events', 'label' => 'RTO', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'return_pct', 'label' => 'Return rate', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'refund_amount', 'label' => 'Refunded', 'format' => 'currency', 'align' => 'right'],
                ]),
            ],
            verdict: $this->verdictFrom($kpis, $totals, $threshold),
            caveats: [
                Caveat::note($filters->usesReturnDateBasis()
                    ? 'Return-date basis compares this window\'s returns against this window\'s sales, which understates the rate while orders are still in the return window.'
                    : 'Order-date basis attributes every return to the order it came from, so recent windows will keep rising as returns arrive.'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $kpis
     * @param  array<string, int>  $totals
     */
    private function verdictFrom(array $kpis, array $totals, float $threshold): Verdict
    {
        $verdict = $kpis['verdict'] ?? null;

        if (is_array($verdict)) {
            return new Verdict(
                $verdict['status'] ?? Verdict::NEUTRAL,
                $verdict['headline'] ?? '',
                $verdict['detail'] ?? null,
                $verdict['action'] ?? null,
                $verdict['impact_paise'] ?? null,
            );
        }

        $rate = Num::pct($totals['returned_amount'] + $totals['rto_amount'], $totals['invoiced_sales']);

        return $rate > $threshold
            ? Verdict::bad(
                sprintf('Returns and RTO are running at %.1f%%', $rate),
                sprintf('%s of invoiced sales came back.', Money::format($totals['returned_amount'] + $totals['rto_amount'])),
                'Start with the worst SKU in the table — one product usually explains most of it.',
                $totals['returned_amount'] + $totals['rto_amount'],
            )
            : Verdict::good(sprintf('Returns are under control at %.1f%%', $rate));
    }
}
