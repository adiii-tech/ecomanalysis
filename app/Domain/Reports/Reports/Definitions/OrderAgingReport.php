<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Operations\Queries\LogisticsQuery;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Support\Money;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * A live queue, not a period report: everything still unshipped, oldest first,
 * with the SLA breaches called out. Late dispatch is the cheapest problem to
 * fix and the most expensive to ignore.
 */
class OrderAgingReport extends Report
{
    public function __construct(
        private readonly LogisticsQuery $logistics,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'order_aging';
    }

    public function label(): string
    {
        return 'Order Aging';
    }

    public function category(): string
    {
        return 'Operations & Inventory';
    }

    public function description(): string
    {
        return 'Unshipped orders by age with SLA breach flags.';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['order_aging'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $aging = $this->logistics->orderAging();
        $buckets = collect($aging['buckets']);
        $rows = collect($aging['rows']);

        $breachedRows = $rows->where('sla_breached', true)->values();

        return new ReportPayload(
            kpis: [
                $this->kpi('unshipped', 'Unshipped orders', (float) $aging['total'], null, 'number', higherIsBetter: false),
                $this->kpi('unshipped_value', 'Value waiting', (float) $buckets->sum('value'), higherIsBetter: false),
                $this->kpi('breached', 'Past dispatch SLA', (float) $aging['breached_count'], null, 'number', higherIsBetter: false,
                    tooltip: sprintf('Your dispatch SLA is %d days.', (int) $aging['sla_days'])),
                $this->kpi('breached_value', 'Value past SLA', (float) $aging['breached_value'], higherIsBetter: false),
            ],
            sections: [
                Section::chart(Section::BAR, 'Orders waiting, by age', $buckets->all(), 'bucket', [
                    ['key' => 'count', 'label' => 'Orders', 'format' => 'number'],
                ]),
                Section::table('Breached first', $breachedRows->all(), $this->columns(),
                    $breachedRows->isEmpty()
                        ? sprintf('Nothing has been waiting longer than your %d-day dispatch SLA.', (int) $aging['sla_days'])
                        : sprintf('%s of value has been sitting past SLA.', Money::format((int) $aging['breached_value'])),
                    exportKey: 'order_aging'),
                $this->tableFromDataset($this->datasets->build('order_aging', $filters), 'The whole queue', exportKey: 'order_aging'),
            ],
            verdict: $this->verdictFrom($aging),
            caveats: [$this->caveatFrom($aging['caveat'] ?? null)],
        );
    }

    /** @return list<array<string, mixed>> */
    private function columns(): array
    {
        return [
            ['key' => 'order_number', 'label' => 'Order', 'format' => 'text'],
            ['key' => 'placed_at', 'label' => 'Placed', 'format' => 'datetime'],
            ['key' => 'age_days', 'label' => 'Waiting (days)', 'format' => 'number', 'align' => 'right'],
            ['key' => 'bucket', 'label' => 'Bucket', 'format' => 'badge'],
            ['key' => 'channel_name', 'label' => 'Channel', 'format' => 'text'],
            ['key' => 'payment_mode', 'label' => 'Payment', 'format' => 'text'],
            ['key' => 'shipping_state', 'label' => 'State', 'format' => 'text'],
            ['key' => 'net_amount', 'label' => 'Value', 'format' => 'currency', 'align' => 'right'],
        ];
    }

    /** @param array<string, mixed> $aging */
    private function verdictFrom(array $aging): Verdict
    {
        $verdict = $aging['verdict'] ?? null;

        if (is_array($verdict)) {
            return new Verdict(
                $verdict['status'] ?? Verdict::NEUTRAL,
                $verdict['headline'] ?? '',
                $verdict['detail'] ?? null,
                $verdict['action'] ?? null,
                $verdict['impact_paise'] ?? null,
            );
        }

        return Verdict::neutral('Nothing unshipped', 'The dispatch queue is empty.');
    }
}
