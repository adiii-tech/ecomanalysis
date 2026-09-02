<?php

declare(strict_types=1);

use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Channel;
use App\Models\CostSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);

    CostSetting::query()->create(['tenant_id' => $this->tenant->id, 'gateway_fee_pct' => 2.0]);

    $this->shopify = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);
    $this->amazon = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Amazon', 'code' => 'amazon', 'type' => ChannelType::Marketplace,
    ]);

    $sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'v1', 'sku_code' => 'KL-102',
        'name' => 'Indigo Kurta', 'mrp' => 150000, 'selling_price' => 100000, 'cost_price' => 40000,
    ]);

    $make = function (Channel $channel, string $state, PaymentMode $mode, int $price, string $number) use ($sku): Order {
        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id, 'channel_id' => $channel->id, 'source' => 'test',
            'external_id' => $number, 'order_number' => $number,
            'placed_at' => CarbonImmutable::now()->subDays(2),
            'status' => OrderStatus::Delivered, 'payment_mode' => $mode,
            'shipping_state' => $state, 'shipping_city' => 'Mumbai',
        ]);

        OrderItem::query()->create([
            'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $sku->id,
            'sku_code' => 'KL-102', 'qty' => 1, 'unit_price' => $price, 'discount' => 0,
            'tax' => 4500, 'cogs_unit' => 40000,
        ]);

        app(ComputeOrderEconomics::class)->handle($order->fresh());

        return $order->fresh();
    };

    $make($this->shopify, 'Maharashtra', PaymentMode::Prepaid, 100000, '#1001');
    $make($this->amazon, 'Bihar', PaymentMode::Cod, 100000, '#1002');
    // Priced under cost, so this one loses money.
    $make($this->amazon, 'Bihar', PaymentMode::Cod, 20000, '#1003');
});

it('returns the orders behind a channel', function (): void {
    $response = $this->actingAs($this->user)->getJson('/api/drilldown/orders?dimension=channel&value=Amazon&preset=last_30_days');

    $response->assertOk();

    expect(collect($response->json('data.rows'))->pluck('order_number'))
        ->toContain('#1002', '#1003')
        ->not->toContain('#1001');
});

it('returns the orders behind a state, a SKU and a payment mode', function (): void {
    $byState = $this->actingAs($this->user)->getJson('/api/drilldown/orders?dimension=state&value=Bihar')->json('data.total');
    $bySku = $this->actingAs($this->user)->getJson('/api/drilldown/orders?dimension=sku&value=KL-102')->json('data.total');
    $byMode = $this->actingAs($this->user)->getJson('/api/drilldown/orders?dimension=payment_mode&value=cod')->json('data.total');

    expect($byState)->toBe(2)
        ->and($bySku)->toBe(3)
        ->and($byMode)->toBe(2);
});

it('does not duplicate an order that has several matching line items', function (): void {
    $order = Order::query()->where('order_number', '#1001')->first();
    $sku = Sku::query()->first();

    OrderItem::query()->create([
        'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $sku->id,
        'sku_code' => 'KL-102', 'qty' => 1, 'unit_price' => 100000, 'discount' => 0, 'tax' => 4500, 'cogs_unit' => 40000,
    ]);

    $rows = $this->actingAs($this->user)->getJson('/api/drilldown/orders?dimension=sku&value=KL-102')->json('data.rows');

    expect(collect($rows)->pluck('order_number')->duplicates())->toBeEmpty();
});

it('narrows to loss-making orders', function (): void {
    $rows = $this->actingAs($this->user)->getJson('/api/drilldown/orders?only=loss')->json('data.rows');

    expect(collect($rows)->pluck('order_number')->all())->toBe(['#1003']);
});

it('refuses a dimension that is not on the whitelist', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/drilldown/orders?dimension=shipping_pincode&value=800001')
        ->assertStatus(422);
});

it('opens one order with its items and shipments', function (): void {
    $order = Order::query()->where('order_number', '#1001')->first();

    $response = $this->actingAs($this->user)->getJson("/api/drilldown/orders/{$order->id}");

    $response->assertOk()
        ->assertJsonPath('data.order.order_number', '#1001')
        ->assertJsonPath('data.items.0.sku_code', 'KL-102');
});

it('keeps drill-down inside the tenant', function (): void {
    $other = $this->tenant(['name' => 'Rival Brand']);
    $rival = $this->userFor($other);

    $rows = $this->actingAs($rival)->getJson('/api/drilldown/orders?dimension=state&value=Bihar')->json('data.rows');

    expect($rows)->toBeEmpty();
});

it('is refused without the orders permission', function (): void {
    $limited = $this->userFor($this->tenant, ['dashboard.kpi_strip.view'], 'ANALYST');

    $this->actingAs($limited)->getJson('/api/drilldown/orders')->assertForbidden();
});
