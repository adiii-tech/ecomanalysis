<?php

declare(strict_types=1);

use App\Domain\Rollups\Actions\RebuildDailyMetrics;
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
use App\Support\Money;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * @return array{tenant: Tenant, channel: Channel, sku: Sku}
 */
function seedSalesFixture(Tenant $tenant): array
{
    CostSetting::query()->create([
        'tenant_id' => $tenant->id,
        'packaging_cost' => Money::fromRupees(20),
        'per_order_fixed_cost' => 0,
        'cod_charge' => Money::fromRupees(30),
        'default_shipping_cost' => Money::fromRupees(80),
        'gateway_fee_pct' => 2.0,
        'return_handling_cost' => Money::fromRupees(100),
        'rto_handling_cost' => Money::fromRupees(150),
    ]);

    $channel = Channel::query()->create([
        'tenant_id' => $tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $sku = Sku::query()->create([
        'tenant_id' => $tenant->id, 'source' => 'test', 'external_id' => 'v1',
        'sku_code' => 'SKU-1', 'name' => 'Test SKU',
        'mrp' => Money::fromRupees(1500), 'selling_price' => Money::fromRupees(1000),
        'cost_price' => Money::fromRupees(400),
    ]);

    return ['tenant' => $tenant, 'channel' => $channel, 'sku' => $sku];
}

function makeOrder(array $fixture, array $attributes = [], int $qty = 1, int $discount = 0): Order
{
    $order = Order::query()->create([
        'tenant_id' => $fixture['tenant']->id,
        'channel_id' => $fixture['channel']->id,
        'source' => 'test',
        'external_id' => 'o-'.uniqid(),
        'order_number' => '#'.random_int(1000, 9999),
        'placed_at' => CarbonImmutable::now()->subDays(2),
        'status' => OrderStatus::Delivered,
        'payment_mode' => PaymentMode::Prepaid,
        'shipping_state' => 'Maharashtra',
        ...$attributes,
    ]);

    OrderItem::query()->create([
        'tenant_id' => $fixture['tenant']->id,
        'order_id' => $order->id,
        'sku_id' => $fixture['sku']->id,
        'sku_code' => 'SKU-1',
        'qty' => $qty,
        'unit_price' => Money::fromRupees(1000),
        'discount' => $discount,
        'cogs_unit' => Money::fromRupees(400),
    ]);

    return app(ComputeOrderEconomics::class)->handle($order->fresh());
}

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->fixture = seedSalesFixture($this->tenant);
});

it('resolves gross down to contribution margin', function (): void {
    $order = makeOrder($this->fixture, [], qty: 2, discount: Money::fromRupees(200));

    // 2 × ₹1000 gross, ₹200 off, ₹800 COGS, ₹80 shipping, ₹20 packaging, 2% gateway.
    expect($order->gross_amount)->toBe(Money::fromRupees(2000))
        ->and($order->discount_amount)->toBe(Money::fromRupees(200))
        ->and($order->invoiced_amount)->toBe(Money::fromRupees(1800))
        ->and($order->net_amount)->toBe(Money::fromRupees(1800))
        ->and($order->cogs_amount)->toBe(Money::fromRupees(800))
        ->and($order->logistics_amount)->toBe(Money::fromRupees(80))
        ->and($order->packaging_amount)->toBe(Money::fromRupees(20))
        ->and($order->gateway_fee_amount)->toBe(Money::fromRupees(36))
        ->and($order->contribution_margin)->toBe(Money::fromRupees(1800 - 800 - 80 - 20 - 36));
});

it('charges a COD fee and no gateway fee on cash orders', function (): void {
    $cod = makeOrder($this->fixture, ['payment_mode' => PaymentMode::Cod]);

    expect($cod->gateway_fee_amount)->toBe(0)
        ->and($cod->logistics_amount)->toBe(Money::fromRupees(80 + 30));
});

it('excludes cancelled orders from invoiced and net sales', function (): void {
    $cancelled = makeOrder($this->fixture, [
        'status' => OrderStatus::Cancelled,
        'cancelled_at' => CarbonImmutable::now()->subDay(),
    ]);

    expect($cancelled->invoiced_amount)->toBe(0)
        ->and($cancelled->net_amount)->toBe(0)
        ->and($cancelled->cancelled_amount)->toBe(Money::fromRupees(1000));
});

it('takes RTO fully off net sales and charges the handling cost', function (): void {
    $rto = makeOrder($this->fixture, ['status' => OrderStatus::Rto, 'is_rto' => true]);

    expect($rto->invoiced_amount)->toBe(Money::fromRupees(1000))
        ->and($rto->rto_amount)->toBe(Money::fromRupees(1000))
        ->and($rto->net_amount)->toBe(0)
        ->and($rto->return_cost_amount)->toBe(Money::fromRupees(150))
        ->and($rto->contribution_margin)->toBeLessThan(0);
});

it('serves KPIs from the rollup with a previous-period comparison', function (): void {
    makeOrder($this->fixture);
    makeOrder($this->fixture);

    app(RebuildDailyMetrics::class)->handle(
        $this->tenant,
        CarbonImmutable::now()->subDays(60),
        CarbonImmutable::now(),
    );

    $user = $this->userFor($this->tenant, ['dashboard.kpi_strip.view']);

    $response = $this->actingAs($user)->getJson('/api/dashboard/kpis?preset=last_30_days');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success', 'statusCode', 'message',
            'data' => [['key', 'label', 'value', 'prev_value', 'delta_pct', 'direction', 'is_good', 'format', 'sparkline']],
            'meta' => ['cached_at', 'period', 'prev_period'],
        ]);

    $keys = collect($response->json('data'))->pluck('key');
    expect($keys)->toContain('gross_sales', 'invoiced_sales', 'net_sales', 'orders', 'contribution_margin_pct', 'aov');

    $netSales = collect($response->json('data'))->firstWhere('key', 'net_sales');
    expect((int) $netSales['value'])->toBe(Money::fromRupees(2000));
});

it('refuses a widget the role does not have', function (): void {
    $user = $this->userFor($this->tenant, ['dashboard.recent_orders.view']);

    $this->actingAs($user)->getJson('/api/dashboard/kpis')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('meta.required_permission', ['dashboard.kpi_strip.view']);
});

it('rejects an unauthenticated request', function (): void {
    $this->getJson('/api/dashboard/kpis')->assertUnauthorized();
});

it('returns a coherent empty payload when there is no data at all', function (): void {
    $user = $this->userFor($this->tenant, ['dashboard.kpi_strip.view', 'dashboard.sales_summary.view']);

    $kpis = $this->actingAs($user)->getJson('/api/dashboard/kpis?preset=last_30_days');
    $kpis->assertOk();
    expect(collect($kpis->json('data'))->every(fn (array $m): bool => (float) $m['value'] === 0.0))->toBeTrue();

    $summary = $this->actingAs($user)->getJson('/api/dashboard/sales-summary?preset=last_30_days');
    $summary->assertOk()->assertJsonPath('data.verdict.status', 'neutral');
});

it('never leaks another tenant rows', function (): void {
    makeOrder($this->fixture);

    $other = Tenant::query()->create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'timezone' => 'Asia/Kolkata']);
    $otherFixture = app(TenantContext::class)->runAs($other, function () use ($other): array {
        $fixture = seedSalesFixture($other);
        makeOrder($fixture);
        makeOrder($fixture);

        return $fixture;
    });

    app(RebuildDailyMetrics::class)->handle($this->tenant, CarbonImmutable::now()->subDays(60), CarbonImmutable::now());
    app(RebuildDailyMetrics::class)->handle($other, CarbonImmutable::now()->subDays(60), CarbonImmutable::now());

    $user = $this->userFor($this->tenant, ['dashboard.kpi_strip.view']);
    $response = $this->actingAs($user)->getJson('/api/dashboard/kpis?preset=last_30_days');

    $orders = collect($response->json('data'))->firstWhere('key', 'orders');
    expect((int) $orders['value'])->toBe(1);

    expect($otherFixture['tenant']->id)->not->toBe($this->tenant->id);
});
