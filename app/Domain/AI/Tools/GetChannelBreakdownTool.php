<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Domain\Sales\Queries\ChannelQuery;
use App\Domain\Sales\Queries\PaymentModeQuery;
use App\Support\WidgetFilters;

class GetChannelBreakdownTool extends BaseMetricTool
{
    public function __construct(
        private readonly ChannelQuery $channels,
        private readonly PaymentModeQuery $payment,
    ) {}

    public function name(): string
    {
        return 'get_channel_breakdown';
    }

    public function description(): string
    {
        return 'Net sales, contribution margin, AOV, return rate and RTO rate for every sales channel (Shopify D2C, Amazon, Flipkart, Myntra, Meesho and so on), plus the same split by COD vs prepaid. Use for "which channel is most profitable" or any COD-versus-prepaid question.';
    }

    public function permission(): string
    {
        return 'dashboard.channel_mix.view';
    }

    /** @param array<string, mixed> $input */
    public function run(array $input, WidgetFilters $filters): array
    {
        $resolved = $this->resolve($input, $filters);
        $mix = $this->channels->mix($resolved);
        $modes = $this->payment->handle($resolved);

        return [
            'period' => $this->describePeriod($resolved),
            'channels' => array_map(fn (array $row): array => [
                'channel' => $row['name'],
                'type' => $row['type'],
                'orders' => $row['orders'],
                'net_sales' => $this->money($row['net_sales']),
                'contribution_margin' => $this->money($row['margin']),
                'margin_pct' => $row['margin_pct'],
                'share_of_net_sales_pct' => $row['share_pct'],
                'aov' => $this->money($row['aov']),
                'return_pct' => $row['return_pct'],
                'rto_pct' => $row['rto_pct'],
            ], $mix['rows']),
            'channel_verdict' => $mix['verdict']['headline'] ?? null,
            'payment_modes' => array_map(fn (array $row): array => [
                'mode' => $row['label'],
                'orders' => $row['orders'],
                'net_sales' => $this->money($row['net_sales']),
                'margin_pct' => $row['net_margin_pct'],
                'rto_pct' => $row['rto_pct'],
                'share_of_orders_pct' => $row['share_of_orders'],
            ], $modes['rows']),
            'payment_verdict' => $modes['verdict']['headline'] ?? null,
        ];
    }
}
