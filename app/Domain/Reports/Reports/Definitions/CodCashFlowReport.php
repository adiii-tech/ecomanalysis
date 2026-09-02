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
 * COD is a loan you make to your courier. This report says how much of it is
 * still outstanding, how long it takes to come back, and which courier is
 * slowest to pay.
 */
class CodCashFlowReport extends Report
{
    public function __construct(private readonly DatasetRegistry $datasets) {}

    public function key(): string
    {
        return 'cod_cash_flow';
    }

    public function label(): string
    {
        return 'COD Cash Flow';
    }

    public function category(): string
    {
        return 'Returns & Cash';
    }

    public function description(): string
    {
        return 'Collected vs remitted, settlement delays and reconciliation.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['cod_cash_flow'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $summary = $this->summary($filters);
        $byCourier = $this->byCourier($filters);
        $ageing = $this->ageing($filters);

        $outstanding = (int) $summary['collected_amount'] - (int) $summary['remitted_amount'];

        return new ReportPayload(
            kpis: [
                $this->kpi('cod_value', 'COD shipped', (float) $summary['total_amount'],
                    tooltip: 'Order value shipped on cash on delivery in this window.'),
                $this->kpi('collected', 'Collected by courier', (float) $summary['collected_amount']),
                $this->kpi('outstanding', 'Awaiting remittance', (float) $outstanding, higherIsBetter: false,
                    tooltip: 'Cash the courier has taken from your customer but has not yet paid you.'),
                $this->kpi('avg_days', 'Average days to remit', (float) $summary['avg_remittance_days'], null, 'days', higherIsBetter: false),
            ],
            sections: [
                Section::callouts('Where the cash is', [
                    ['label' => 'In transit (not yet collected)', 'value' => (int) $summary['in_transit_amount'], 'format' => 'currency',
                        'note' => sprintf('%d shipments', (int) $summary['in_transit_count'])],
                    ['label' => 'Collected, awaiting remittance', 'value' => $outstanding, 'format' => 'currency',
                        'tone' => $outstanding > 0 ? 'watch' : 'good', 'note' => 'Your money, held by the courier.'],
                    ['label' => 'Remitted to you', 'value' => (int) $summary['remitted_amount'], 'format' => 'currency', 'tone' => 'good'],
                    ['label' => 'Lost to RTO', 'value' => (int) $summary['rto_amount'], 'format' => 'currency', 'tone' => 'bad',
                        'note' => 'Never collected — the parcel came back.'],
                ]),
                Section::table('By courier', $byCourier, [
                    ['key' => 'courier', 'label' => 'Courier', 'format' => 'text'],
                    ['key' => 'shipments', 'label' => 'COD shipments', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'collected_amount', 'label' => 'Collected', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'remitted_amount', 'label' => 'Remitted', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'outstanding', 'label' => 'Outstanding', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'avg_remittance_days', 'label' => 'Avg days to remit', 'format' => 'number', 'align' => 'right'],
                ], 'A courier that collects fast but remits slowly is financing itself with your cash.'),
                Section::chart(Section::BAR, 'How long remittance is taking', $ageing, 'bucket', [
                    ['key' => 'amount', 'label' => 'Outstanding', 'format' => 'currency'],
                ], 'Outstanding COD by how long it has been sitting since collection.'),
                $this->tableFromDataset($this->datasets->build('cod_cash_flow', $filters), 'Every COD shipment', exportKey: 'cod_cash_flow'),
            ],
            verdict: $this->verdict($summary, $byCourier, $outstanding, $ageing),
            caveats: [
                Caveat::note('Collection and remittance timestamps come from the courier feed. A courier that does not report remittance shows as collected and outstanding indefinitely.'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function summary(WidgetFilters $filters): array
    {
        $row = $this->base($filters)
            ->selectRaw('COUNT(*) AS shipments, COALESCE(SUM(o.net_amount),0) AS total_amount')
            ->selectRaw('SUM(CASE WHEN s.cod_collected_at IS NOT NULL THEN o.net_amount ELSE 0 END) AS collected_amount')
            ->selectRaw('SUM(CASE WHEN s.cod_remitted_at IS NOT NULL THEN o.net_amount ELSE 0 END) AS remitted_amount')
            ->selectRaw('SUM(CASE WHEN s.cod_collected_at IS NULL AND s.is_rto = 0 THEN o.net_amount ELSE 0 END) AS in_transit_amount')
            ->selectRaw('SUM(CASE WHEN s.cod_collected_at IS NULL AND s.is_rto = 0 THEN 1 ELSE 0 END) AS in_transit_count')
            ->selectRaw('SUM(CASE WHEN s.is_rto = 1 THEN o.net_amount ELSE 0 END) AS rto_amount')
            ->selectRaw('AVG(DATEDIFF(s.cod_remitted_at, s.cod_collected_at)) AS avg_remittance_days')
            ->first();

        return [
            'shipments' => (int) ($row->shipments ?? 0),
            'total_amount' => (int) ($row->total_amount ?? 0),
            'collected_amount' => (int) ($row->collected_amount ?? 0),
            'remitted_amount' => (int) ($row->remitted_amount ?? 0),
            'in_transit_amount' => (int) ($row->in_transit_amount ?? 0),
            'in_transit_count' => (int) ($row->in_transit_count ?? 0),
            'rto_amount' => (int) ($row->rto_amount ?? 0),
            'avg_remittance_days' => round((float) ($row->avg_remittance_days ?? 0), 1),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function byCourier(WidgetFilters $filters): array
    {
        return $this->base($filters)
            ->whereNotNull('s.courier')
            ->selectRaw('s.courier, COUNT(*) AS shipments')
            ->selectRaw('SUM(CASE WHEN s.cod_collected_at IS NOT NULL THEN o.net_amount ELSE 0 END) AS collected_amount')
            ->selectRaw('SUM(CASE WHEN s.cod_remitted_at IS NOT NULL THEN o.net_amount ELSE 0 END) AS remitted_amount')
            ->selectRaw('AVG(DATEDIFF(s.cod_remitted_at, s.cod_collected_at)) AS avg_remittance_days')
            ->groupBy('s.courier')
            ->orderByDesc('collected_amount')
            ->get()
            ->map(static fn (object $row): array => [
                'courier' => $row->courier,
                'shipments' => (int) $row->shipments,
                'collected_amount' => (int) $row->collected_amount,
                'remitted_amount' => (int) $row->remitted_amount,
                'outstanding' => (int) $row->collected_amount - (int) $row->remitted_amount,
                'avg_remittance_days' => round((float) ($row->avg_remittance_days ?? 0), 1),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function ageing(WidgetFilters $filters): array
    {
        $rows = $this->base($filters)
            ->whereNotNull('s.cod_collected_at')
            ->whereNull('s.cod_remitted_at')
            ->selectRaw('DATEDIFF(NOW(), s.cod_collected_at) AS days, o.net_amount')
            ->get();

        $buckets = ['0-7 days' => 0, '8-15 days' => 0, '16-30 days' => 0, '30+ days' => 0];

        foreach ($rows as $row) {
            $days = (int) $row->days;
            $bucket = match (true) {
                $days <= 7 => '0-7 days',
                $days <= 15 => '8-15 days',
                $days <= 30 => '16-30 days',
                default => '30+ days',
            };
            $buckets[$bucket] += (int) $row->net_amount;
        }

        return array_map(
            static fn (string $bucket, int $amount): array => ['bucket' => $bucket, 'amount' => $amount],
            array_keys($buckets),
            array_values($buckets),
        );
    }

    private function base(WidgetFilters $filters): Builder
    {
        $query = DB::table('shipments as s')
            ->join('orders as o', 'o.id', '=', 's.order_id')
            ->where('s.tenant_id', Tenant::id())
            ->where('o.payment_mode', 'cod')
            ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')]);

        if ($filters->channelIds !== []) {
            $query->whereIn('o.channel_id', $filters->channelIds);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $byCourier
     * @param  list<array<string, mixed>>  $ageing
     */
    private function verdict(array $summary, array $byCourier, int $outstanding, array $ageing): Verdict
    {
        if ((int) $summary['shipments'] === 0) {
            return Verdict::neutral('No COD shipments', 'Everything in this window was prepaid.');
        }

        $stale = collect($ageing)->firstWhere('bucket', '30+ days');

        if ($stale !== null && (int) $stale['amount'] > 0) {
            return Verdict::bad(
                sprintf('%s has been sitting with couriers for over 30 days', Money::format((int) $stale['amount'])),
                'Cash collected from your customers that has not reached your account.',
                'Raise a remittance reconciliation with the courier — this is working capital you have already earned.',
                (int) $stale['amount'],
            );
        }

        $rtoShare = Num::pct((int) $summary['rto_amount'], (int) $summary['total_amount']);

        if ($rtoShare > 15) {
            return Verdict::watch(
                sprintf('%.1f%% of COD value came back as RTO', $rtoShare),
                sprintf('%s never got collected because the parcel returned.', Money::format((int) $summary['rto_amount'])),
                'Tighten COD on the states in the RTO report before this grows.',
            );
        }

        return Verdict::good(
            sprintf('COD is remitting in %.1f days on average', (float) $summary['avg_remittance_days']),
            sprintf('%s outstanding right now, nothing older than 30 days.', Money::format($outstanding)),
        );
    }
}
