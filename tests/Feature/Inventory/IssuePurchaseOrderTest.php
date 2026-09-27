<?php

declare(strict_types=1);

use App\Models\PurchaseOrder;
use App\Models\Sku;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant, ['catalog.restock.view', 'catalog.purchase_orders.manage', 'catalog.suppliers.view']);

    $this->supplier = Supplier::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Jaipur Textiles', 'lead_time_days' => 10,
    ]);

    $this->sku = function (string $code, ?Supplier $supplier = null, int $cost = 100): Sku {
        return Sku::query()->create([
            'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => 'v-'.$code,
            'sku_code' => $code, 'name' => $code.' product', 'supplier_id' => $supplier?->id,
            'cost_price' => Money::fromRupees($cost), 'selling_price' => Money::fromRupees($cost * 2),
        ]);
    };

    $this->issue = function (array $groups) {
        return $this->actingAs($this->user)->postJson('/api/purchasing/orders/issue', ['groups' => $groups]);
    };
});

it('carries the supplier onto the restock row so the desk can group by vendor', function (): void {
    ($this->sku)('WITH-SUP', $this->supplier);
    ($this->sku)('NO-SUP', null);

    $rows = collect(
        $this->actingAs($this->user)->getJson('/api/restock?window=90')->assertOk()->json('data.rows'),
    )->keyBy('sku_code');

    expect($rows['WITH-SUP']['supplier_id'])->toBe($this->supplier->id)
        ->and($rows['WITH-SUP']['supplier_name'])->toBe('Jaipur Textiles')
        ->and($rows['NO-SUP']['supplier_id'])->toBeNull();
});

it('creates a purchase order already sent, with incoming stock bumped', function (): void {
    $sku = ($this->sku)('ISSUE-1', $this->supplier, cost: 150);

    $response = ($this->issue)([
        ['supplier_id' => $this->supplier->id, 'expected_at' => '2026-10-15', 'items' => [
            ['sku_id' => $sku->id, 'quantity' => 40, 'unit_cost' => 150],
        ]],
    ])->assertOk();

    $orderId = $response->json('data.orders.0.id');
    $order = PurchaseOrder::query()->with('items')->find($orderId);

    expect($order->status)->toBe('sent')
        ->and($order->sent_at)->not->toBeNull()
        ->and($order->supplier_id)->toBe($this->supplier->id)
        ->and($order->total)->toBe(Money::fromRupees(40 * 150))
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity_ordered)->toBe(40)
        ->and(DB::table('inventory')->where('sku_id', $sku->id)->where('source', 'manual')->value('incoming'))->toBe(40);
});

it('issues one purchase order per supplier in the same request', function (): void {
    $other = Supplier::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Chennai Leather']);
    $skuA = ($this->sku)('MULTI-A', $this->supplier);
    $skuB = ($this->sku)('MULTI-B', $other);

    $response = ($this->issue)([
        ['supplier_id' => $this->supplier->id, 'expected_at' => null, 'items' => [['sku_id' => $skuA->id, 'quantity' => 10, 'unit_cost' => 100]]],
        ['supplier_id' => $other->id, 'expected_at' => null, 'items' => [['sku_id' => $skuB->id, 'quantity' => 5, 'unit_cost' => 200]]],
    ])->assertOk();

    $orders = $response->json('data.orders');

    expect($orders)->toHaveCount(2)
        ->and(collect($orders)->pluck('po_number')->unique())->toHaveCount(2)
        ->and(collect($orders)->pluck('supplier_id')->sort()->values()->all())->toBe(collect([$this->supplier->id, $other->id])->sort()->values()->all())
        ->and(PurchaseOrder::query()->count())->toBe(2);
});

it('spreads no freight onto a restock-issued order — the desk decided the price already', function (): void {
    $sku = ($this->sku)('FLAT-1', $this->supplier, cost: 200);

    $response = ($this->issue)([
        ['supplier_id' => $this->supplier->id, 'expected_at' => null, 'items' => [['sku_id' => $sku->id, 'quantity' => 10, 'unit_cost' => 200]]],
    ])->assertOk();

    $order = PurchaseOrder::query()->with('items')->find($response->json('data.orders.0.id'));

    expect($order->items->first()->landed_unit_cost)->toBe(Money::fromRupees(200))
        ->and($order->freight_cost)->toBe(0);
});

it('refuses a supplier id that belongs to another tenant', function (): void {
    $sku = ($this->sku)('CROSS-1', $this->supplier);

    $other = Tenant::query()->create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'timezone' => 'Asia/Kolkata']);
    $foreignSupplier = app(TenantContext::class)->runAs($other, fn () => Supplier::query()->create(['tenant_id' => $other->id, 'name' => 'Foreign Co']));

    ($this->issue)([
        ['supplier_id' => $foreignSupplier->id, 'expected_at' => null, 'items' => [['sku_id' => $sku->id, 'quantity' => 10, 'unit_cost' => 100]]],
    ])->assertStatus(422);

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('refuses a sku id that belongs to another tenant', function (): void {
    $other = Tenant::query()->create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'timezone' => 'Asia/Kolkata']);
    $foreignSku = app(TenantContext::class)->runAs($other, fn () => Sku::query()->create([
        'tenant_id' => $other->id, 'source' => 'shopify', 'external_id' => 'v-foreign',
        'sku_code' => 'FOREIGN-1', 'name' => 'Foreign', 'cost_price' => Money::fromRupees(100), 'selling_price' => Money::fromRupees(200),
    ]));

    ($this->issue)([
        ['supplier_id' => $this->supplier->id, 'expected_at' => null, 'items' => [['sku_id' => $foreignSku->id, 'quantity' => 10, 'unit_cost' => 100]]],
    ])->assertStatus(422);

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('will not issue without the purchase-order-management permission', function (): void {
    $viewer = $this->userFor($this->tenant, ['catalog.restock.view']);
    $sku = ($this->sku)('LOCKED-1', $this->supplier);

    $this->actingAs($viewer)
        ->postJson('/api/purchasing/orders/issue', ['groups' => [
            ['supplier_id' => $this->supplier->id, 'expected_at' => null, 'items' => [['sku_id' => $sku->id, 'quantity' => 5, 'unit_cost' => 100]]],
        ]])
        ->assertForbidden();
});

it('rejects a group with no items and a request with no groups', function (): void {
    ($this->issue)([])->assertStatus(422);

    ($this->issue)([
        ['supplier_id' => $this->supplier->id, 'expected_at' => null, 'items' => []],
    ])->assertStatus(422);
});
