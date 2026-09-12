<?php

declare(strict_types=1);

use App\Domain\Rollups\Jobs\RebuildRollups;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Domain\Sales\Actions\UpsertShopifyOrder;
use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Enums\PaymentMode;
use App\Models\Connector;
use App\Models\CostSetting;
use App\Models\Sku;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->tenant = $this->tenant();

    CostSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'cod_charge' => Money::fromRupees(35),
        'gateway_fee_pct' => 2.0,
    ]);

    Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '111',
        'sku_code' => 'SKU-1', 'name' => 'Test SKU', 'selling_price' => 100000, 'cost_price' => 40000,
    ]);

    $this->payloadFor = fn (int $id, array $gateways): array => [
        'id' => $id,
        'name' => '#'.$id,
        'created_at' => '2026-09-01T10:00:00+05:30',
        'updated_at' => '2026-09-01T10:00:00+05:30',
        'financial_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'INR',
        'total_tax' => '0.00',
        'payment_gateway_names' => $gateways,
        'total_shipping_price_set' => ['shop_money' => ['amount' => '0.00']],
        'shipping_address' => ['city' => 'Delhi', 'province' => 'Delhi', 'zip' => '110001'],
        'line_items' => [[
            'id' => 8000 + $id, 'variant_id' => 111, 'sku' => 'SKU-1', 'title' => 'Test SKU',
            'quantity' => 1, 'price' => '1000.00', 'discount_allocations' => [], 'tax_lines' => [],
        ]],
        'refunds' => [],
    ];
});

it('reads the payment mode however the checkout spells the gateway', function (array $gateways, PaymentMode $expected): void {
    $order = app(UpsertShopifyOrder::class)->handle($this->tenant, ($this->payloadFor)(1001, $gateways));

    expect($order->payment_mode)->toBe($expected);
})->with([
    'shopify underscores' => [['cash_on_delivery'], PaymentMode::Cod],
    'manual method' => [['Cash on Delivery (COD)'], PaymentMode::Cod],
    'partial prepaid cod' => [['Gokwik PPCOD'], PaymentMode::Cod],
    'pay on delivery' => [['Pay on Delivery'], PaymentMode::Cod],
    'upi' => [['Gokwik UPI'], PaymentMode::Prepaid],
    'card gateway' => [['razorpay'], PaymentMode::Prepaid],
    'returns app' => [['ReturnPrime'], PaymentMode::Prepaid],
]);

it('keeps the gateway name on the order', function (): void {
    $order = app(UpsertShopifyOrder::class)->handle($this->tenant, ($this->payloadFor)(1002, ['Gokwik UPI']));

    expect($order->payment_gateway)->toBe('Gokwik UPI');
});

it('corrects orders that were stored with the wrong payment mode', function (): void {
    Connector::query()->create([
        'tenant_id' => $this->tenant->id,
        'connector_id' => 'shopify',
        'status' => ConnectorStatus::Connected,
        'auth_type' => AuthType::OAuth,
        'credentials' => ['shop_domain' => 'kaira.myshopify.com', 'access_token' => 't'],
    ]);

    $order = app(UpsertShopifyOrder::class)->handle($this->tenant, ($this->payloadFor)(1003, ['cash_on_delivery']));

    // What the old rule left behind: a COD order sitting in prepaid, and priced as prepaid.
    $order->forceFill(['payment_mode' => PaymentMode::Prepaid, 'payment_gateway' => null])->save();
    $wrong = app(ComputeOrderEconomics::class)->handle($order->fresh());

    Queue::fake();
    Http::fake([
        '*/orders.json*' => Http::response(['orders' => [['id' => 1003, 'payment_gateway_names' => ['cash_on_delivery']]]]),
    ]);

    $this->artisan('shopify:refresh-payment-modes', ['tenant' => $this->tenant->id])->assertSuccessful();

    $fresh = $order->fresh();

    expect($fresh->payment_mode)->toBe(PaymentMode::Cod)
        ->and($fresh->payment_gateway)->toBe('cash_on_delivery')
        // The COD charge only lands once the mode is right.
        ->and($fresh->logistics_amount)->toBeGreaterThan($order->logistics_amount);

    Queue::assertPushed(RebuildRollups::class);
});
