<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Actions;

use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\MarketplaceFee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Normalises a marketplace sale order (Unicommerce today, direct Amazon /
 * Flipkart later) into the same canonical schema Shopify orders use, so both
 * live side by side in every rollup.
 *
 * @phpstan-type NormalisedOrder array{
 *     external_id: string, order_number: string, channel_code: string, channel_name: string,
 *     placed_at: string, status: string, payment_mode: string, state: ?string, city: ?string,
 *     pincode: ?string, currency: string, shipping_amount: float, tax_amount: float,
 *     items: list<array<string, mixed>>, fees: list<array{type: string, amount: float}>
 * }
 */
class UpsertMarketplaceOrder
{
    public function __construct(private readonly ComputeOrderEconomics $economics) {}

    /** @param NormalisedOrder $payload */
    public function handle(Tenant $tenant, string $source, array $payload): Order
    {
        return DB::transaction(function () use ($tenant, $source, $payload): Order {
            $channel = $this->resolveChannel($tenant, $source, $payload);

            $order = Order::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'source' => $source, 'external_id' => $payload['external_id']],
                [
                    'channel_id' => $channel->id,
                    'order_number' => $payload['order_number'],
                    'placed_at' => Carbon::parse($payload['placed_at']),
                    'invoiced_at' => Carbon::parse($payload['placed_at']),
                    'status' => $payload['status'],
                    'payment_mode' => $payload['payment_mode'],
                    'shipping_state' => $payload['state'] ?? null,
                    'shipping_city' => $payload['city'] ?? null,
                    'shipping_pincode' => $payload['pincode'] ?? null,
                    'currency' => $payload['currency'] ?? 'INR',
                    'shipping_amount' => $this->paise($payload['shipping_amount'] ?? 0),
                    'tax_amount' => $this->paise($payload['tax_amount'] ?? 0),
                    'is_rto' => ($payload['status'] ?? '') === 'rto',
                    'cancelled_at' => ($payload['status'] ?? '') === 'cancelled' ? Carbon::parse($payload['placed_at']) : null,
                ],
            );

            $this->syncItems($tenant, $order, $payload['items'] ?? []);
            $this->syncFees($tenant, $order, $channel, $payload['fees'] ?? []);

            return $this->economics->handle($order);
        });
    }

    /** @param NormalisedOrder $payload */
    private function resolveChannel(Tenant $tenant, string $source, array $payload): Channel
    {
        return Channel::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $payload['channel_code']],
            [
                'name' => $payload['channel_name'],
                'type' => ChannelType::Marketplace,
                'source_connector' => $source,
                'color' => $this->colorFor($payload['channel_code']),
            ],
        );
    }

    /** @param list<array<string, mixed>> $items */
    private function syncItems(Tenant $tenant, Order $order, array $items): void
    {
        $keep = [];

        foreach ($items as $line) {
            $sku = empty($line['sku_code']) ? null : Sku::query()
                ->where('tenant_id', $tenant->id)
                ->where('sku_code', $line['sku_code'])
                ->first();

            $item = OrderItem::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'order_id' => $order->id, 'external_id' => (string) $line['external_id']],
                [
                    'sku_id' => $sku?->id,
                    'sku_code' => $line['sku_code'] ?? null,
                    'title' => $line['title'] ?? null,
                    'qty' => (int) ($line['qty'] ?? 1),
                    'unit_price' => $this->paise($line['unit_price'] ?? 0),
                    'discount' => $this->paise($line['discount'] ?? 0),
                    'tax' => $this->paise($line['tax'] ?? 0),
                    'status' => $line['status'] ?? $order->status->value,
                ],
            );

            $keep[] = $item->id;
        }

        OrderItem::query()->where('order_id', $order->id)->whereNotIn('id', $keep ?: [0])->delete();
    }

    /** @param list<array{type: string, amount: float}> $fees */
    private function syncFees(Tenant $tenant, Order $order, Channel $channel, array $fees): void
    {
        MarketplaceFee::query()->where('order_id', $order->id)->delete();

        foreach ($fees as $fee) {
            if ((float) $fee['amount'] === 0.0) {
                continue;
            }

            MarketplaceFee::query()->create([
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'channel_id' => $channel->id,
                'fee_type' => $fee['type'],
                'amount' => $this->paise($fee['amount']),
                'fee_date' => $order->placed_at->toDateString(),
            ]);
        }
    }

    private function colorFor(string $code): string
    {
        return match (true) {
            str_contains($code, 'amazon') => '#ff9900',
            str_contains($code, 'flipkart') => '#2874f0',
            str_contains($code, 'myntra') => '#ff3f6c',
            str_contains($code, 'meesho') => '#570d63',
            str_contains($code, 'nykaa') => '#fc2779',
            str_contains($code, 'ajio') => '#2a2a2a',
            default => '#64748b',
        };
    }

    private function paise(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
