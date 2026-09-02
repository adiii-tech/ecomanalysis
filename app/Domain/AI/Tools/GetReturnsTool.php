<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Domain\Operations\Queries\LogisticsQuery;
use App\Domain\Operations\Queries\ReturnsQuery;
use App\Domain\Sales\Queries\GeoQuery;
use App\Support\WidgetFilters;

class GetReturnsTool extends BaseMetricTool
{
    public function __construct(
        private readonly ReturnsQuery $returns,
        private readonly GeoQuery $geo,
        private readonly LogisticsQuery $logistics,
    ) {}

    public function name(): string
    {
        return 'get_returns_and_rto';
    }

    public function description(): string
    {
        return 'Returns and RTO: rate, cost, top reasons, worst SKUs, worst states, and courier performance. RTO means the parcel came back without ever being delivered, which is different from a customer return. Use for any returns, RTO, courier or delivery question.';
    }

    public function permission(): string
    {
        return 'operations.returns_kpis.view';
    }

    /** @return array<string, mixed> */
    protected function properties(): array
    {
        return [
            ...$this->periodProperties(),
            'view' => [
                'type' => 'string',
                'enum' => ['summary', 'reasons', 'by_state', 'by_channel', 'couriers'],
                'description' => 'Which slice to return. Defaults to summary.',
            ],
        ];
    }

    /** @param array<string, mixed> $input */
    public function run(array $input, WidgetFilters $filters): array
    {
        $resolved = $this->resolve($input, $filters);

        return match ($input['view'] ?? 'summary') {
            'reasons' => [
                'period' => $this->describePeriod($resolved),
                'reasons' => array_map(fn (array $row): array => [
                    'reason' => $row['label'],
                    'returns' => $row['count'],
                    'refunded' => $this->money($row['refund_amount']),
                ], $this->returns->byReason($resolved, 10)->all()),
                'worst_skus' => array_map(fn (array $row): array => [
                    'sku' => $row['sku_code'],
                    'name' => $row['name'],
                    'returns' => $row['returns_count'],
                    'refunded' => $this->money($row['refund_amount']),
                ], $this->returns->topReturnSkus($resolved, 10)->all()),
            ],

            'by_state' => (function () use ($resolved): array {
                $rto = $this->geo->rtoByState($resolved, 15.0, 20);

                return [
                    'period' => $this->describePeriod($resolved),
                    'threshold_pct' => $rto['threshold'],
                    'caveat' => $rto['caveat'],
                    'verdict' => $rto['verdict']['headline'] ?? null,
                    'states' => array_map(static fn (array $row): array => [
                        'state' => $row['state'],
                        'orders' => $row['orders'],
                        'rto_count' => $row['rto_count'],
                        'rto_pct' => $row['rto_pct'],
                        'cod_share_pct' => $row['cod_share_pct'],
                    ], $rto['rows']),
                ];
            })(),

            'by_channel' => [
                'period' => $this->describePeriod($resolved),
                'channels' => array_map(fn (array $row): array => [
                    'channel' => $row['name'],
                    'orders' => $row['orders'],
                    'customer_returns' => $row['customer_returns'],
                    'rto_events' => $row['rto_events'],
                    'return_pct' => $row['return_pct'],
                ], $this->returns->byChannel($resolved)['rows']),
            ],

            'couriers' => (function () use ($resolved): array {
                $scorecard = $this->logistics->courierScorecard($resolved);

                return [
                    'period' => $this->describePeriod($resolved),
                    'caveat' => $scorecard['caveat'],
                    'verdict' => $scorecard['verdict']['headline'] ?? null,
                    'couriers' => array_map(fn (array $row): array => [
                        'courier' => $row['courier'],
                        'shipments' => $row['shipments'],
                        'delivered_pct' => $row['delivered_pct'],
                        'on_time_pct' => $row['on_time_pct'],
                        'rto_pct' => $row['rto_pct'],
                        'avg_days' => $row['avg_days'],
                        'cost_per_shipment' => $this->money($row['cost_per_shipment']),
                        'score' => $row['score'],
                    ], $scorecard['rows']),
                ];
            })(),

            default => (function () use ($resolved): array {
                $kpis = $this->returns->kpis($resolved);

                return [
                    'period' => $this->describePeriod($resolved),
                    'returns_basis' => $kpis['basis'],
                    'customer_returns' => $kpis['returns']['value'],
                    'rto_events' => $kpis['rto_events']['value'],
                    'return_rate_pct' => $kpis['return_rate']['value'],
                    'total_return_cost' => $this->money((int) $kpis['return_loss']['value']),
                    'verdict' => $kpis['verdict']['headline'] ?? null,
                ];
            })(),
        };
    }
}
