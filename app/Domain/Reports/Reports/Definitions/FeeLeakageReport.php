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
 * Where the marketplaces take their cut. Commission, fixed, shipping and
 * settlement fees, in rupees and as a share of what you invoiced.
 */
class FeeLeakageReport extends Report
{
    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'fee_leakage';
    }

    public function label(): string
    {
        return 'Fee Leakage';
    }

    public function category(): string
    {
        return 'Profit & Margin';
    }

    public function description(): string
    {
        return 'Commission, fixed, shipping and settlement fees per channel.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['fee_leakage'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $dataset = $this->datasets->build('fee_leakage', $filters);
        $rows = $dataset->rows->map(static fn (array|object $row): array => (array) $row)->values()->all();

        $totals = $this->rollups->totals($filters);
        $previous = $this->rollups->totals($filters->previous());

        $byType = collect($rows)->groupBy('fee_type')->map(static fn ($group, string $type): array => [
            'fee_type' => $type,
            'amount' => (int) $group->sum('amount'),
            'occurrences' => (int) $group->sum('occurrences'),
        ])->sortByDesc('amount')->values()->all();

        $byChannel = collect($rows)->groupBy('channel')->map(static fn ($group, string $channel): array => [
            'channel' => $channel ?: 'Unattributed',
            'amount' => (int) $group->sum('amount'),
        ])->sortByDesc('amount')->values()->all();

        $reportedFees = $this->sumRows($rows, 'amount');

        return new ReportPayload(
            kpis: [
                $this->kpi('marketplace_fees', 'Marketplace Fees', (float) $totals['marketplace_fees'], (float) $previous['marketplace_fees'], higherIsBetter: false),
                $this->kpi('gateway_fees', 'Gateway Fees', (float) $totals['gateway_fees'], (float) $previous['gateway_fees'], higherIsBetter: false),
                $this->kpi('logistics_cost', 'Logistics', (float) $totals['logistics_cost'], (float) $previous['logistics_cost'], higherIsBetter: false),
                $this->kpi('fee_load', 'Total fee load', Num::pct(
                    $totals['marketplace_fees'] + $totals['gateway_fees'] + $totals['logistics_cost'],
                    $totals['invoiced_sales'],
                ), Num::pct(
                    $previous['marketplace_fees'] + $previous['gateway_fees'] + $previous['logistics_cost'],
                    $previous['invoiced_sales'],
                ), 'percent', higherIsBetter: false, tooltip: 'Every fee as a share of invoiced sales.'),
            ],
            sections: [
                $this->tableFromDataset($dataset, 'Fees line by line', exportKey: 'fee_leakage'),
                Section::chart(Section::BAR, 'Fees by type', $byType, 'fee_type', [
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'currency'],
                ]),
                Section::chart(Section::BAR, 'Fees by channel', $byChannel, 'channel', [
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'currency'],
                ]),
                $this->unreconciledSection($filters, $reportedFees, $totals['marketplace_fees']),
            ],
            verdict: $this->verdict($byType, $totals),
            caveats: array_filter([
                $rows === [] && $totals['marketplace_fees'] > 0
                    ? Caveat::partial(sprintf(
                        'Your rollups carry %s of marketplace fees, but no connector reported a fee breakdown, so the table below is empty. The totals above are still right.',
                        Money::format($totals['marketplace_fees']),
                    ))
                    : null,
            ]),
        );
    }

    /**
     * Fees the rollups know about but no marketplace statement explained. This
     * gap is the honest measure of how much of your fee bill is a black box.
     */
    private function unreconciledSection(WidgetFilters $filters, int $reported, int $rolledUp): Section
    {
        $gap = $rolledUp - $reported;

        $orders = (int) DB::table('orders')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->count();

        return Section::callouts('Reconciliation', [
            ['label' => 'Fees in your rollups', 'value' => $rolledUp, 'format' => 'currency'],
            ['label' => 'Fees itemised by marketplaces', 'value' => $reported, 'format' => 'currency'],
            ['label' => 'Unexplained gap', 'value' => $gap, 'format' => 'currency', 'tone' => abs($gap) > $rolledUp * 0.1 ? 'bad' : 'good',
                'note' => $gap === 0 ? 'Every rupee of fees is itemised.' : 'Fees charged that no statement line accounts for.'],
            ['label' => 'Fee per order', 'value' => (int) round(Num::safeDivide($rolledUp, $orders)), 'format' => 'currency'],
        ], 'Statement lines against what the rollups actually deducted.');
    }

    /**
     * @param  list<array<string, mixed>>  $byType
     * @param  array<string, int>  $totals
     */
    private function verdict(array $byType, array $totals): Verdict
    {
        $feeLoad = Num::pct(
            $totals['marketplace_fees'] + $totals['gateway_fees'] + $totals['logistics_cost'],
            $totals['invoiced_sales'],
        );

        if ($totals['invoiced_sales'] === 0) {
            return Verdict::neutral('Nothing invoiced', 'No sales in this window, so no fees to judge.');
        }

        $worst = $byType[0] ?? null;

        if ($feeLoad > 30) {
            return Verdict::bad(
                sprintf('Fees are eating %.1f%% of invoiced sales', $feeLoad),
                $worst !== null
                    ? sprintf('%s alone accounts for %s.', ucfirst((string) $worst['fee_type']), Money::format((int) $worst['amount']))
                    : null,
                'Renegotiate the largest fee type or move volume to a cheaper channel — at this load, growth makes the problem bigger.',
                (int) round($totals['invoiced_sales'] * ($feeLoad - 30) / 100),
            );
        }

        if ($feeLoad > 20) {
            return Verdict::watch(
                sprintf('Fee load is %.1f%% of invoiced sales', $feeLoad),
                $worst !== null ? sprintf('Biggest single line is %s.', ucfirst((string) $worst['fee_type'])) : null,
                'Worth a rate conversation with your largest marketplace.',
            );
        }

        return Verdict::good(
            sprintf('Fee load is under control at %.1f%%', $feeLoad),
            'Nothing here is unusual for the channel mix you run.',
        );
    }
}
