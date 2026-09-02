<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every payment, refund and failure with its gateway and fee. Failed payments
 * are the interesting part: they are orders a customer tried to give you.
 */
class TransactionLedgerReport extends Report
{
    public function __construct(private readonly DatasetRegistry $datasets) {}

    public function key(): string
    {
        return 'transaction_ledger';
    }

    public function label(): string
    {
        return 'Transaction Ledger';
    }

    public function category(): string
    {
        return 'Returns & Cash';
    }

    public function description(): string
    {
        return 'Every payment, refund and failure with gateway and status.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['transactions'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $summary = $this->summary($filters);
        $byGateway = $this->byGateway($filters);
        $failures = $this->failures($filters);

        $captured = (int) $summary['captured_amount'];
        $refunded = (int) $summary['refunded_amount'];
        $fees = (int) $summary['fee_amount'];
        $failedValue = (int) $summary['failed_amount'];

        return new ReportPayload(
            kpis: [
                $this->kpi('captured', 'Captured', (float) $captured),
                $this->kpi('refunded', 'Refunded', (float) $refunded, higherIsBetter: false),
                $this->kpi('fees', 'Gateway fees', (float) $fees, higherIsBetter: false,
                    tooltip: sprintf('%.2f%% of captured value.', Num::pct($fees, $captured))),
                $this->kpi('success_rate', 'Payment success rate',
                    Num::pct((int) $summary['captured_count'], (int) $summary['captured_count'] + (int) $summary['failed_count']),
                    null, 'percent', tooltip: 'Successful captures as a share of all payment attempts.'),
            ],
            sections: [
                Section::table('By gateway', $byGateway, [
                    ['key' => 'gateway', 'label' => 'Gateway', 'format' => 'text'],
                    ['key' => 'captured_count', 'label' => 'Captures', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'captured_amount', 'label' => 'Captured', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'fee_amount', 'label' => 'Fees', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'fee_pct', 'label' => 'Effective fee', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'failed_count', 'label' => 'Failures', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'success_pct', 'label' => 'Success rate', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'refunded_amount', 'label' => 'Refunded', 'format' => 'currency', 'align' => 'right'],
                ], 'The effective fee is what you actually paid, not the rate card.'),
                Section::table('Why payments failed', $failures, [
                    ['key' => 'failure_reason', 'label' => 'Reason', 'format' => 'text'],
                    ['key' => 'attempts', 'label' => 'Attempts', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'amount', 'label' => 'Value not captured', 'format' => 'currency', 'align' => 'right'],
                ], sprintf('%s of attempted payments did not go through.', Money::format($failedValue))),
                $this->tableFromDataset($this->datasets->build('transactions', $filters), 'The ledger', exportKey: 'transactions'),
            ],
            verdict: $this->verdict($summary, $byGateway, $failedValue),
            caveats: [
                Caveat::note('COD orders have no gateway transaction — the courier collects the cash, so they appear in the COD cash flow report instead.'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function summary(WidgetFilters $filters): array
    {
        $row = $this->base($filters)
            ->selectRaw("SUM(CASE WHEN status = 'captured' THEN amount ELSE 0 END) AS captured_amount")
            ->selectRaw("SUM(CASE WHEN status = 'captured' THEN 1 ELSE 0 END) AS captured_count")
            ->selectRaw("SUM(CASE WHEN kind = 'refund' THEN amount ELSE 0 END) AS refunded_amount")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) AS failed_amount")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count")
            ->selectRaw('COALESCE(SUM(fee),0) AS fee_amount')
            ->first();

        return [
            'captured_amount' => (int) ($row->captured_amount ?? 0),
            'captured_count' => (int) ($row->captured_count ?? 0),
            'refunded_amount' => (int) ($row->refunded_amount ?? 0),
            'failed_amount' => (int) ($row->failed_amount ?? 0),
            'failed_count' => (int) ($row->failed_count ?? 0),
            'fee_amount' => (int) ($row->fee_amount ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function byGateway(WidgetFilters $filters): array
    {
        return $this->base($filters)
            ->whereNotNull('gateway')
            ->selectRaw('gateway')
            ->selectRaw("SUM(CASE WHEN status = 'captured' THEN amount ELSE 0 END) AS captured_amount")
            ->selectRaw("SUM(CASE WHEN status = 'captured' THEN 1 ELSE 0 END) AS captured_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count")
            ->selectRaw("SUM(CASE WHEN kind = 'refund' THEN amount ELSE 0 END) AS refunded_amount")
            ->selectRaw('COALESCE(SUM(fee),0) AS fee_amount')
            ->groupBy('gateway')
            ->orderByDesc('captured_amount')
            ->get()
            ->map(static function (object $row): array {
                $captured = (int) $row->captured_amount;
                $attempts = (int) $row->captured_count + (int) $row->failed_count;

                return [
                    'gateway' => $row->gateway,
                    'captured_amount' => $captured,
                    'captured_count' => (int) $row->captured_count,
                    'failed_count' => (int) $row->failed_count,
                    'refunded_amount' => (int) $row->refunded_amount,
                    'fee_amount' => (int) $row->fee_amount,
                    'fee_pct' => Num::pct((int) $row->fee_amount, $captured),
                    'success_pct' => Num::pct((int) $row->captured_count, $attempts),
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function failures(WidgetFilters $filters): array
    {
        return $this->base($filters)
            ->where('status', 'failed')
            ->selectRaw("COALESCE(NULLIF(failure_reason, ''), 'Not reported') AS failure_reason")
            ->selectRaw('COUNT(*) AS attempts, COALESCE(SUM(amount),0) AS amount')
            ->groupByRaw("COALESCE(NULLIF(failure_reason, ''), 'Not reported')")
            ->orderByDesc('amount')
            ->get()
            ->map(static fn (object $row): array => [
                'failure_reason' => $row->failure_reason,
                'attempts' => (int) $row->attempts,
                'amount' => (int) $row->amount,
            ])
            ->all();
    }

    private function base(WidgetFilters $filters): Builder
    {
        return DB::table('transactions')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('processed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')]);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $byGateway
     */
    private function verdict(array $summary, array $byGateway, int $failedValue): Verdict
    {
        $attempts = (int) $summary['captured_count'] + (int) $summary['failed_count'];

        if ($attempts === 0) {
            return Verdict::neutral('No gateway transactions', 'Every order in this window was cash on delivery.');
        }

        $successRate = Num::pct((int) $summary['captured_count'], $attempts);

        if ($successRate < 90 && $failedValue > 0) {
            $worst = collect($byGateway)->sortBy('success_pct')->first();

            return Verdict::bad(
                sprintf('%.1f%% of payment attempts failed', 100 - $successRate),
                sprintf('%s of orders your customers tried to pay for did not go through%s.',
                    Money::format($failedValue),
                    $worst !== null ? sprintf(', worst on %s at %.1f%% success', $worst['gateway'], (float) $worst['success_pct']) : ''),
                'Check the failure reasons above with your gateway — a bank-side decline pattern is usually fixable.',
                $failedValue,
            );
        }

        $feePct = Num::pct((int) $summary['fee_amount'], (int) $summary['captured_amount']);

        if ($feePct > 2.5) {
            return Verdict::watch(
                sprintf('Gateway fees are %.2f%% of captured value', $feePct),
                sprintf('%s in fees on %s captured.', Money::format((int) $summary['fee_amount']), Money::format((int) $summary['captured_amount'])),
                'At your volume that rate is negotiable — the table above shows which gateway costs most.',
            );
        }

        return Verdict::good(
            sprintf('Payments are clearing at %.1f%%', $successRate),
            sprintf('%s captured with %.2f%% in fees.', Money::format((int) $summary['captured_amount']), $feePct),
        );
    }
}
