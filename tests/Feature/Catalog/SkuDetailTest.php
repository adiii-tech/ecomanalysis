<?php

declare(strict_types=1);

use App\Models\Sku;
use App\Models\SkuCostHistory;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant, ['catalog.products.view']);

    $this->sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => 'v1',
        'sku_code' => 'KL-102', 'name' => 'Indigo Kurta', 'category' => 'Ethnic Wear',
        'cost_price' => Money::fromRupees(400), 'selling_price' => Money::fromRupees(1000),
    ]);

    $this->detail = function () {
        return $this->actingAs($this->user)->getJson("/api/catalog/skus/{$this->sku->id}")->assertOk()->json('data');
    };
});

it('gives the same coverage numbers the catalog tables already show', function (): void {
    DB::table('inventory')->insert([
        'tenant_id' => $this->tenant->id, 'sku_id' => $this->sku->id, 'location_id' => null, 'source' => 'shopify',
        'on_hand' => 20, 'available' => 20, 'reserved' => 0, 'incoming' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('sku_daily_rollup')->insert([
        'tenant_id' => $this->tenant->id, 'sku_id' => $this->sku->id, 'channel_id' => null,
        'date' => now()->subDays(2)->toDateString(), 'units_sold' => 6, 'orders_count' => 6,
        'net_sales' => Money::fromRupees(6000), 'computed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $data = ($this->detail)();

    expect($data['sku']['sku_code'])->toBe('KL-102')
        ->and($data['sku']['stock'])->toBe(20)
        ->and($data['sku']['units_30d'])->toBe(6)
        ->and($data['sku']['stock_value'])->toBe(20 * Money::fromRupees(400))
        ->and((float) $data['sku']['margin_pct'])->toBe(60.0);
});

it('carries the last twelve months of sales alongside the current numbers', function (): void {
    DB::table('sku_daily_rollup')->insert([
        'tenant_id' => $this->tenant->id, 'sku_id' => $this->sku->id, 'channel_id' => null,
        'date' => now()->subDays(5)->toDateString(), 'units_sold' => 3, 'orders_count' => 3,
        'net_sales' => Money::fromRupees(3000), 'computed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $data = ($this->detail)();

    expect($data['history']['months'])->toHaveCount(12)
        ->and($data['history']['lifetime_units'])->toBe(3);
});

it('shows the cost history behind the current cost, newest first', function (): void {
    SkuCostHistory::query()->create([
        'tenant_id' => $this->tenant->id, 'sku_id' => $this->sku->id,
        'cost_price' => Money::fromRupees(350), 'effective_from' => now()->subMonths(2), 'note' => 'Initial cost',
    ]);
    SkuCostHistory::query()->create([
        'tenant_id' => $this->tenant->id, 'sku_id' => $this->sku->id,
        'cost_price' => Money::fromRupees(400), 'effective_from' => now()->subDays(5), 'note' => 'Supplier price rise',
    ]);

    $data = ($this->detail)();

    expect($data['cost_history'])->toHaveCount(2)
        ->and($data['cost_history'][0]['note'])->toBe('Supplier price rise')
        ->and($data['cost_history'][0]['cost_price'])->toBe(Money::fromRupees(400));
});

it('returns 404 for a SKU that does not exist', function (): void {
    $this->actingAs($this->user)->getJson('/api/catalog/skus/999999')->assertNotFound();
});

it('never shows another tenant a SKU by id', function (): void {
    $other = $this->tenant(['name' => 'Rival Brand']);
    $rivalSku = Sku::query()->create([
        'tenant_id' => $other->id, 'source' => 'shopify', 'external_id' => 'rv1',
        'sku_code' => 'RV-1', 'name' => 'Rival Product',
        'cost_price' => Money::fromRupees(100), 'selling_price' => Money::fromRupees(200),
    ]);

    $this->actingAs($this->user)->getJson("/api/catalog/skus/{$rivalSku->id}")->assertNotFound();
});

it('will not show sku detail to a role without catalog access', function (): void {
    $viewer = $this->userFor($this->tenant, ['dashboard.kpi_strip.view']);

    $this->actingAs($viewer)->getJson("/api/catalog/skus/{$this->sku->id}")->assertForbidden();
});
