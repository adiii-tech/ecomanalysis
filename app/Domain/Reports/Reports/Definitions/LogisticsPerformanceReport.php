<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Operations\Queries\LogisticsQuery;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Sales\Queries\GeoQuery;
use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * Couriers, transit times and RTO by state in one place, because the three
 * only make sense together: the cheapest courier is rarely the cheapest once
 * its returns are counted.
 */
class LogisticsPerformanceReport extends Report
{
    public function __construct(
        private readonly LogisticsQuery $logistics,
        private readonly GeoQuery $geo,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'logistics_performance';
    }

    public function label(): string
    {
        return 'Logistics Performance';
    }

    public function category(): string
    {
        return 'Operations & Inventory';
    }

    public function description(): string
    {
        return 'Shipment status, courier performance and RTO by state.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['courier_scorecard', 'rto_by_state', 'ndr_queue'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $kpis = $this->logistics->kpis($filters);
        $scorecard = $this->logistics->courierScorecard($filters);
        $delivery = $this->logistics->deliveryPerformance($filters);
        $status = $this->logistics->shipmentStatus($filters);
        $ndr = $this->logistics->ndrQueue($filters, 100);

        $threshold = (float) Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()])->rto_threshold_pct;
        $rto = $this->geo->rtoByState($filters, $threshold, 25);

        return new ReportPayload(
            kpis: [
                $this->kpi('shipments', 'Shipments', (float) $kpis['total_shipments']['value'], (float) $kpis['total_shipments']['prev_value'], 'number'),
                $this->kpi('delivered_pct', 'Delivered', (float) $kpis['delivered_pct']['value'], (float) $kpis['delivered_pct']['prev_value'], 'percent'),
                $this->kpi('on_time_pct', 'On-time vs SLA', (float) $delivery['on_time_pct'], null, 'percent',
                    tooltip: sprintf('Delivered within your %d-day promise.', (int) $delivery['sla_days'])),
                $this->kpi('returned_pct', 'RTO rate', (float) $kpis['returned_pct']['value'], (float) $kpis['returned_pct']['prev_value'], 'percent', higherIsBetter: false),
            ],
            sections: [
                $this->tableFromDataset($this->datasets->build('courier_scorecard', $filters), 'Courier scorecard', exportKey: 'courier_scorecard',
                    subtitle: 'One score per courier: delivery rate, on-time share and RTO, weighted.',
                    drilldown: ['dimension' => 'courier', 'value_key' => 'courier']),
                Section::chart(Section::BAR, 'Transit time distribution', collect($delivery['distribution'])->map(static fn (array $row): array => [
                    'day' => 'Day '.$row['day'],
                    'count' => $row['count'],
                ])->all(), 'day', [
                    ['key' => 'count', 'label' => 'Deliveries', 'format' => 'number'],
                ], sprintf('Median %.1f days against a %d-day SLA.', (float) $delivery['median_days'], (int) $delivery['sla_days'])),
                $this->tableFromDataset($this->datasets->build('rto_by_state', $filters), 'RTO by state', exportKey: 'rto_by_state',
                    subtitle: sprintf('Anything above your %.0f%% threshold is flagged.', $threshold),
                    drilldown: ['dimension' => 'state', 'value_key' => 'state']),
                Section::table('Shipment status by courier', collect($status['rows'])->all(), $this->statusColumns($status),
                    caveat: $this->caveatFrom($status['caveat'] ?? null)),
                $this->tableFromDataset($this->datasets->build('ndr_queue', $filters), 'NDR queue', exportKey: 'ndr_queue',
                    subtitle: sprintf('%d shipments need a decision, %s of order value at stake.',
                        (int) $ndr['count'], Money::format((int) $ndr['value_at_risk']))),
            ],
            verdict: $this->verdict($scorecard, $ndr, $rto, $threshold),
            caveats: [
                $this->caveatFrom($scorecard['caveat'] ?? null),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $status
     * @return list<array<string, mixed>>
     */
    private function statusColumns(array $status): array
    {
        $columns = [['key' => 'courier', 'label' => 'Courier', 'format' => 'text']];

        foreach ($status['statuses'] as $state) {
            $columns[] = ['key' => $state['key'], 'label' => $state['label'], 'format' => 'number', 'align' => 'right'];
        }

        $columns[] = ['key' => 'total', 'label' => 'Total', 'format' => 'number', 'align' => 'right'];

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $scorecard
     * @param  array<string, mixed>  $ndr
     * @param  array<string, mixed>  $rto
     */
    private function verdict(array $scorecard, array $ndr, array $rto, float $threshold): Verdict
    {
        $couriers = collect($scorecard['rows'] ?? []);

        if ((int) $ndr['count'] > 0 && (int) $ndr['value_at_risk'] > 0) {
            return Verdict::bad(
                sprintf('%d shipments are stuck in NDR', (int) $ndr['count']),
                sprintf('%s of order value becomes RTO if nobody calls the customer.', Money::format((int) $ndr['value_at_risk'])),
                'Work the NDR queue before the third delivery attempt — after that the courier returns it automatically.',
                (int) $ndr['value_at_risk'],
            );
        }

        $breaching = collect($rto['rows'] ?? [])->filter(static fn (array $row): bool => (float) ($row['rto_pct'] ?? 0) > $threshold);

        if ($breaching->isNotEmpty()) {
            $worst = $breaching->first();

            return Verdict::watch(
                sprintf('%s is over your %.0f%% RTO limit', $worst['state'] ?? '—', $threshold),
                sprintf('%.1f%% of its shipments come back.', (float) ($worst['rto_pct'] ?? 0)),
                'Prepaid-only for that state, or switch the courier serving it.',
            );
        }

        if ($couriers->count() < 2) {
            return Verdict::neutral('Only one courier in play', 'There is nothing to compare until a second carrier has volume.');
        }

        $best = $couriers->first();
        $worst = $couriers->last();

        return Verdict::good(
            sprintf('%s is your strongest courier', $best['courier']),
            sprintf('%.1f%% on time with %.1f%% RTO, against %s at %.1f%% and %.1f%%.',
                (float) $best['on_time_pct'], (float) $best['rto_pct'], $worst['courier'], (float) $worst['on_time_pct'], (float) $worst['rto_pct']),
            sprintf('Shift volume from %s where the lanes overlap.', $worst['courier']),
        );
    }
}
