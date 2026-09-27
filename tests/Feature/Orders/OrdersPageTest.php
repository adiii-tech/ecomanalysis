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
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant, ['dashboard.recent_orders.view']);

    CostSetting::query()->create(['tenant_id' => $this->tenant->id, 'gateway_fee_pct' => 2.0]);

    $this->channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $this->sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'v1', 'sku_code' => 'KL-102',
        'name' => 'Indigo Kurta', 'mrp' => 150000, 'selling_price' => 100000, 'cost_price' => 40000,
    ]);

    $this->order = function (string $number, OrderStatus $status, int $daysAgo = 2) {
        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'source' => 'test',
            'external_id' => $number, 'order_number' => $number,
            'placed_at' => CarbonImmutable::now()->subDays($daysAgo),
            'status' => $status, 'payment_mode' => PaymentMode::Prepaid,
            'shipping_state' => 'Maharashtra', 'shipping_city' => 'Mumbai',
        ]);

        OrderItem::query()->create([
            'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $this->sku->id,
            'sku_code' => 'KL-102', 'qty' => 1, 'unit_price' => 100000, 'discount' => 0,
            'tax' => 4500, 'cogs_unit' => 40000,
        ]);

        app(ComputeOrderEconomics::class)->handle($order->fresh());

        return $order->fresh();
    };

    $this->list = function (array $params = []) {
        return $this->actingAs($this->user)->getJson('/api/orders?'.http_build_query($params))->assertOk()->json('data');
    };
});

it('lists orders newest first with the drilldown row shape', function (): void {
    ($this->order)('#1001', OrderStatus::Delivered, daysAgo: 5);
    ($this->order)('#1002', OrderStatus::Shipped, daysAgo: 1);

    $data = ($this->list)();

    expect($data['total'])->toBe(2)
        ->and($data['rows'][0]['order_number'])->toBe('#1002')
        ->and($data['rows'][0]['status_label'])->toBe('Shipped')
        ->and($data['rows'][0]['channel']['name'])->toBe('Shopify')
        ->and($data['rows'][0])->toHaveKeys(['net_amount', 'contribution_margin', 'margin_pct', 'is_rto', 'has_return']);
});

it('narrows to a single status', function (): void {
    ($this->order)('#1001', OrderStatus::Delivered);
    ($this->order)('#1002', OrderStatus::Rto);
    ($this->order)('#1003', OrderStatus::Rto);

    $data = ($this->list)(['status' => 'rto']);

    expect($data['total'])->toBe(2)
        ->and(collect($data['rows'])->pluck('status')->unique()->all())->toBe(['rto']);
});

it('counts every status in the window, not just the one selected', function (): void {
    ($this->order)('#1001', OrderStatus::Delivered);
    ($this->order)('#1002', OrderStatus::Rto);
    ($this->order)('#1003', OrderStatus::Rto);

    $data = ($this->list)(['status' => 'rto']);

    expect($data['status_counts'])->toBe(['delivered' => 1, 'rto' => 2]);
});

it('rejects a status that does not exist', function (): void {
    $this->actingAs($this->user)->getJson('/api/orders?status=shipped-ish')->assertStatus(422);
});

it('caps the list and says how many were left out', function (): void {
    foreach (range(1, 6) as $i) {
        ($this->order)('#100'.$i, OrderStatus::Delivered);
    }

    $data = ($this->list)(['limit' => 4]);

    expect($data['total'])->toBe(6)
        ->and($data['shown'])->toBe(4)
        ->and($data['truncated'])->toBeTrue();
});

it('opens the same order detail the drilldown already serves', function (): void {
    $order = ($this->order)('#2001', OrderStatus::Shipped);

    $detail = $this->actingAs($this->user)->getJson("/api/drilldown/orders/{$order->id}")->assertOk()->json('data');

    expect($detail['order']['order_number'])->toBe('#2001')
        ->and($detail['order']['status'])->toBe('Shipped')
        ->and($detail['items'])->toHaveCount(1)
        ->and($detail['items'][0]['sku_code'])->toBe('KL-102');
});

it('will not show the orders page to a role without the permission', function (): void {
    $viewer = $this->userFor($this->tenant, ['catalog.restock.view']);

    $this->actingAs($viewer)->getJson('/api/orders')->assertForbidden();
});

it('never leaks another tenant orders', function (): void {
    ($this->order)('#3001', OrderStatus::Delivered);

    $other = Tenant::query()->create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'timezone' => 'Asia/Kolkata']);
    app(TenantContext::class)->runAs($other, function () use ($other): void {
        $channel = Channel::query()->create(['tenant_id' => $other->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c]);
        Order::query()->create([
            'tenant_id' => $other->id, 'channel_id' => $channel->id, 'source' => 'test',
            'external_id' => 'foreign-1', 'order_number' => '#F-1',
            'placed_at' => CarbonImmutable::now()->subDay(), 'status' => OrderStatus::Delivered,
            'payment_mode' => PaymentMode::Prepaid, 'shipping_state' => 'Delhi',
        ]);
    });

    $data = ($this->list)();

    expect($data['total'])->toBe(1)
        ->and($data['rows'][0]['order_number'])->toBe('#3001');
});
