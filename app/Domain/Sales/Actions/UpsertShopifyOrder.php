<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\ReturnType;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Sku;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Normalises a raw Shopify order payload into the canonical schema. Idempotent
 * on (tenant_id, source, external_id) so replaying a webhook is safe.
 */
class UpsertShopifyOrder
{
    public function __construct(private readonly ComputeOrderEconomics $economics) {}

    /** @param array<string, mixed> $payload */
    public function handle(Tenant $tenant, array $payload): Order
    {
        return DB::transaction(function () use ($tenant, $payload): Order {
            $channel = $this->resolveChannel($tenant);
            $customer = $this->resolveCustomer($tenant, $payload);
            $shipping = $payload['shipping_address'] ?? $payload['billing_address'] ?? [];

            $order = Order::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'source' => 'shopify', 'external_id' => (string) $payload['id']],
                [
                    'channel_id' => $channel->id,
                    'customer_id' => $customer?->id,
                    'order_number' => $payload['name'] ?? null,
                    'placed_at' => Carbon::parse($payload['created_at']),
                    'invoiced_at' => $payload['processed_at'] ?? $payload['created_at'],
                    'cancelled_at' => $payload['cancelled_at'] ?? null,
                    'status' => $this->resolveStatus($payload),
                    'fulfillment_status' => $payload['fulfillment_status'] ?? 'unfulfilled',
                    'payment_mode' => $this->resolvePaymentMode($payload),
                    'shipping_state' => $shipping['province'] ?? null,
                    'shipping_city' => $shipping['city'] ?? null,
                    'shipping_pincode' => $shipping['zip'] ?? null,
                    'currency' => $payload['currency'] ?? 'INR',
                    'shipping_amount' => $this->paise(Arr::get($payload, 'total_shipping_price_set.shop_money.amount', 0)),
                    'tax_amount' => $this->paise($payload['total_tax'] ?? 0),
                    'discount_codes' => collect($payload['discount_codes'] ?? [])->pluck('code')->implode(','),
                    'utm_source' => Arr::get($payload, 'note_attributes.0.value'),
                    'is_rto' => false,
                ],
            );

            $this->syncItems($tenant, $order, $payload['line_items'] ?? []);
            $this->syncRefunds($tenant, $order, $payload['refunds'] ?? []);
            $this->syncTransactions($tenant, $order, $payload['transactions'] ?? []);
            $this->markFirstOrder($order);

            return $this->economics->handle($order);
        });
    }

    private function resolveChannel(Tenant $tenant): Channel
    {
        return Channel::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'shopify'],
            ['name' => 'Shopify (D2C)', 'type' => 'd2c', 'source_connector' => 'shopify', 'color' => '#16a34a'],
        );
    }

    /** @param array<string, mixed> $payload */
    private function resolveCustomer(Tenant $tenant, array $payload): ?Customer
    {
        $raw = $payload['customer'] ?? null;
        $email = $raw['email'] ?? $payload['email'] ?? null;

        if ($raw === null && $email === null) {
            return null;
        }

        $externalId = isset($raw['id']) ? (string) $raw['id'] : 'email:'.hash('crc32b', (string) $email);
        $address = $raw['default_address'] ?? $payload['shipping_address'] ?? [];

        return Customer::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'source' => 'shopify', 'external_id' => $externalId],
            [
                'email_hash' => Customer::hashIdentity($email),
                'masked_email' => Customer::maskEmail($email),
                'email_encrypted' => $email,
                'phone_hash' => Customer::hashIdentity($raw['phone'] ?? null),
                'masked_phone' => Customer::maskPhone($raw['phone'] ?? null),
                'phone_encrypted' => $raw['phone'] ?? null,
                'name' => trim((string) ($raw['first_name'] ?? '').' '.(string) ($raw['last_name'] ?? '')) ?: null,
                'city' => $address['city'] ?? null,
                'state' => $address['province'] ?? null,
                'pincode' => $address['zip'] ?? null,
            ],
        );
    }

    /** @param list<array<string, mixed>> $lineItems */
    private function syncItems(Tenant $tenant, Order $order, array $lineItems): void
    {
        $keep = [];

        foreach ($lineItems as $line) {
            $sku = $this->resolveSku($tenant, $line);
            $discount = collect($line['discount_allocations'] ?? [])->sum(fn ($a): float => (float) $a['amount']);

            $item = OrderItem::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'order_id' => $order->id, 'external_id' => (string) $line['id']],
                [
                    'sku_id' => $sku?->id,
                    'sku_code' => $line['sku'] ?: ($sku?->sku_code),
                    'title' => $line['title'] ?? null,
                    'qty' => (int) ($line['quantity'] ?? 1),
                    'unit_price' => $this->paise($line['price'] ?? 0),
                    'discount' => $this->paise($discount),
                    'tax' => $this->paise(collect($line['tax_lines'] ?? [])->sum(fn ($t): float => (float) $t['price'])),
                    'status' => $order->status->value,
                ],
            );

            $keep[] = $item->id;
        }

        OrderItem::query()->where('order_id', $order->id)->whereNotIn('id', $keep ?: [0])->delete();
    }

    /** @param array<string, mixed> $line */
    private function resolveSku(Tenant $tenant, array $line): ?Sku
    {
        if (! empty($line['variant_id'])) {
            $sku = Sku::query()
                ->where('tenant_id', $tenant->id)
                ->where('source', 'shopify')
                ->where('external_id', (string) $line['variant_id'])
                ->first();

            if ($sku !== null) {
                return $sku;
            }
        }

        if (empty($line['sku'])) {
            return null;
        }

        return Sku::query()
            ->where('tenant_id', $tenant->id)
            ->where('sku_code', $line['sku'])
            ->first();
    }

    /** @param list<array<string, mixed>> $refunds */
    private function syncRefunds(Tenant $tenant, Order $order, array $refunds): void
    {
        foreach ($refunds as $refund) {
            foreach ($refund['refund_line_items'] ?? [] as $refundLine) {
                $item = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->where('external_id', (string) Arr::get($refundLine, 'line_item_id'))
                    ->first();

                OrderReturn::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'source' => 'shopify', 'external_id' => (string) $refundLine['id']],
                    [
                        'order_id' => $order->id,
                        'order_item_id' => $item?->id,
                        'sku_id' => $item?->sku_id,
                        'channel_id' => $order->channel_id,
                        'type' => ReturnType::CustomerReturn,
                        'reason_code' => $refund['note'] ? str($refund['note'])->slug('_')->limit(60, '')->toString() : 'unspecified',
                        'reason_text' => $refund['note'] ?? null,
                        'qty' => (int) ($refundLine['quantity'] ?? 1),
                        'initiated_at' => Carbon::parse($refund['created_at']),
                        'received_at' => Carbon::parse($refund['processed_at'] ?? $refund['created_at']),
                        'refund_amount' => $this->paise(Arr::get($refundLine, 'subtotal_set.shop_money.amount', $refundLine['subtotal'] ?? 0)),
                        'restock' => (bool) ($refundLine['restock_type'] ?? false),
                        'shipping_state' => $order->shipping_state,
                    ],
                );

                if ($item !== null) {
                    $item->increment('returned_qty', (int) ($refundLine['quantity'] ?? 1));
                }
            }
        }
    }

    /** @param list<array<string, mixed>> $transactions */
    private function syncTransactions(Tenant $tenant, Order $order, array $transactions): void
    {
        foreach ($transactions as $txn) {
            Transaction::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'source' => 'shopify', 'external_id' => (string) $txn['id']],
                [
                    'order_id' => $order->id,
                    'gateway' => $txn['gateway'] ?? null,
                    'method' => Arr::get($txn, 'payment_details.credit_card_company') ?? ($txn['gateway'] ?? null),
                    'kind' => $txn['kind'] ?? 'sale',
                    'amount' => $this->paise($txn['amount'] ?? 0),
                    'fee' => $this->paise(Arr::get($txn, 'receipt.fee', 0)),
                    'status' => $txn['status'] ?? 'success',
                    'failure_reason' => $txn['error_code'] ?? null,
                    'processed_at' => Carbon::parse($txn['processed_at'] ?? $txn['created_at']),
                ],
            );
        }
    }

    private function markFirstOrder(Order $order): void
    {
        if ($order->customer_id === null) {
            return;
        }

        $earliest = Order::query()
            ->where('customer_id', $order->customer_id)
            ->orderBy('placed_at')
            ->value('id');

        $order->forceFill(['is_first_order' => $earliest === $order->id])->save();
    }

    /** @param array<string, mixed> $payload */
    private function resolveStatus(array $payload): OrderStatus
    {
        if (! empty($payload['cancelled_at'])) {
            return OrderStatus::Cancelled;
        }

        $financial = $payload['financial_status'] ?? null;
        $fulfillment = $payload['fulfillment_status'] ?? null;

        return match (true) {
            $financial === 'refunded' => OrderStatus::Returned,
            $fulfillment === 'fulfilled' => OrderStatus::Delivered,
            $fulfillment === 'partial' => OrderStatus::Shipped,
            default => OrderStatus::Confirmed,
        };
    }

    /** @param array<string, mixed> $payload */
    private function resolvePaymentMode(array $payload): PaymentMode
    {
        $gateways = strtolower(implode(' ', $payload['payment_gateway_names'] ?? []));

        return str_contains($gateways, 'cash on delivery') || str_contains($gateways, 'cod')
            ? PaymentMode::Cod
            : PaymentMode::Prepaid;
    }

    private function paise(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
