<?php

declare(strict_types=1);

use App\Domain\Connectors\Actions\RunConnectorSync;
use App\Domain\Inventory\Services\StockLedger;
use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Enums\StockMovementType;
use App\Models\Connector;
use App\Models\Sku;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);

    $this->sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '111',
        'sku_code' => 'FMH-BCC-BSR', 'name' => 'Black Satin Bow Clip',
        'cost_price' => Money::fromRupees(120), 'selling_price' => Money::fromRupees(399),
    ]);

    $this->insertShopifyStock = fn (int $units): bool => DB::table('inventory')->insert([
        'tenant_id' => $this->tenant->id, 'sku_id' => $this->sku->id, 'location_id' => null, 'source' => 'shopify',
        'on_hand' => $units, 'reserved' => 0, 'available' => $units, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

it('shows the stock Shopify reports when none is managed here', function (): void {
    ($this->insertShopifyStock)(42);

    $data = $this->actingAs($this->user)->getJson('/api/inventory/levels')->assertOk()->json('data');
    $row = collect($data['rows'])->firstWhere('sku_id', $this->sku->id);

    expect($row['on_hand'])->toBe(42)
        ->and($row['stock_source'])->toBe('shopify')
        ->and($data['summary']['units'])->toBe(42);
});

it('prefers stock managed here over what the channel reports', function (): void {
    ($this->insertShopifyStock)(42);
    app(StockLedger::class)->record($this->sku, StockMovementType::Opening, 30);

    $row = collect($this->actingAs($this->user)->getJson('/api/inventory/levels')->json('data.rows'))
        ->firstWhere('sku_id', $this->sku->id);

    expect($row['on_hand'])->toBe(30)
        ->and($row['stock_source'])->toBe('manual');
});

it('updates Shopify stock in place instead of adding a row on every sync', function (): void {
    Connector::query()->create([
        'tenant_id' => $this->tenant->id,
        'connector_id' => 'shopify',
        'status' => ConnectorStatus::Connected,
        'auth_type' => AuthType::OAuth,
        'credentials' => ['shop_domain' => 'kaira.myshopify.com', 'access_token' => 't'],
    ]);

    // Two copies left behind by the old sync, which keyed rows on a NULL location.
    ($this->insertShopifyStock)(5);
    ($this->insertShopifyStock)(7);

    Http::fake([
        '*/products.json*' => Http::sequence()
            ->push(['products' => [['id' => 1, 'variants' => [['id' => 111, 'inventory_quantity' => 40]]]]])
            ->push(['products' => [['id' => 1, 'variants' => [['id' => 111, 'inventory_quantity' => 35]]]]]),
    ]);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'inventory', 'manual');
    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'inventory', 'manual');

    $rows = DB::table('inventory')->where('sku_id', $this->sku->id)->where('source', 'shopify')->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->on_hand)->toBe(35);
});
