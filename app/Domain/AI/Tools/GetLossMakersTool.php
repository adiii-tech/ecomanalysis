<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Domain\Sales\Queries\HealthFlagsQuery;
use App\Domain\Sales\Queries\OrderQuery;
use App\Support\WidgetFilters;

class GetLossMakersTool extends BaseMetricTool
{
    public function __construct(
        private readonly OrderQuery $orders,
        private readonly HealthFlagsQuery $flags,
    ) {}

    public function name(): string
    {
        return 'get_problems';
    }

    public function description(): string
    {
        return 'Everything currently going wrong, ranked: loss-making orders, margin drops, RTO spikes, stockouts, SLA breaches and ad anomalies — each with an estimated rupee impact. Use this for "what should I fix first" or "what is wrong" questions.';
    }

    public function permission(): string
    {
        return 'dashboard.health_flags.view';
    }

    /** @param array<string, mixed> $input */
    public function run(array $input, WidgetFilters $filters): array
    {
        $resolved = $this->resolve($input, $filters);
        $losses = $this->orders->lossMaking($resolved, 10);

        return [
            'period' => $this->describePeriod($resolved),
            'health_flags' => array_map(fn (array $flag): array => [
                'severity' => $flag['severity'],
                'title' => $flag['title'],
                'detail' => $flag['body'],
                'estimated_impact' => $flag['impact_amount'] === null ? null : $this->money($flag['impact_amount']),
            ], $this->flags->handle($resolved)),
            'loss_making_orders' => [
                'count' => $losses['count'],
                'total_loss' => $this->money($losses['total_loss']),
                'verdict' => $losses['verdict']['headline'] ?? null,
                'worst' => array_map(fn (array $row): array => [
                    'order' => $row['order_number'],
                    'channel' => $row['channel']['name'] ?? null,
                    'payment' => $row['payment_mode'],
                    'state' => $row['shipping_state'],
                    'net_sales' => $this->money($row['net_amount']),
                    'loss' => $this->money($row['contribution_margin']),
                ], array_slice($losses['rows'], 0, 5)),
            ],
        ];
    }
}
