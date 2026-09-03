<?php

declare(strict_types=1);

use App\Domain\Inventory\Services\StockLedger;
use App\Enums\StockMovementType;
use App\Models\PurchaseOrder;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Money;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);
    $this->ledger = app(StockLedger::class);

    $this->supplier = Supplier::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Jaipur Textiles', 'lead_time_days' => 10,
    ]);

    $this->kurta = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'KL-102',
        'name' => 'Indigo Kurta', 'cost_price' => Money::fromRupees(800),
    ]);

    $this->dupatta = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'KL-210',
        'name' => 'Cotton Dupatta', 'cost_price' => Money::fromRupees(200),
    ]);
});

function draftOrder(array $overrides = []): array
{
    return test()->actingAs(test()->user)->postJson('/api/purchasing/orders', [
        'supplier_id' => test()->supplier->id,
        'expected_at' => now()->addDays(10)->toDateString(),
        'freight_cost' => 2000,
        'items' => [
            ['sku_id' => test()->kurta->id, 'quantity' => 100, 'unit_cost' => 780],
            ['sku_id' => test()->dupatta->id, 'quantity' => 50, 'unit_cost' => 190],
        ],
        ...$overrides,
    ])->json('data');
}

it('creates a purchase order and spreads freight across the lines', function (): void {
    $created = draftOrder();

    $order = PurchaseOrder::query()->with('items')->find($created['id']);

    // 100 × ₹780 = ₹78,000 and 50 × ₹190 = ₹9,500, so freight splits 89% / 11%.
    expect($order->subtotal)->toBe(Money::fromRupees(87500))
        ->and($order->total)->toBe(Money::fromRupees(89500))
        ->and($order->status)->toBe('draft');

    $kurtaLine = $order->items->firstWhere('sku_id', $this->kurta->id);

    expect($kurtaLine->landed_unit_cost)->toBeGreaterThan($kurtaLine->unit_cost)
        ->and($kurtaLine->landed_unit_cost)->toBe(Money::fromRupees(797.83));
});

it('will not receive an order that has not been sent', function (): void {
    $created = draftOrder();

    $this->actingAs($this->user)
        ->postJson("/api/purchasing/orders/{$created['id']}/receive", ['received' => [1 => 10]])
        ->assertStatus(422);
});

it('shows sent quantities as incoming', function (): void {
    $created = draftOrder();

    $this->actingAs($this->user)->postJson("/api/purchasing/orders/{$created['id']}/send")->assertOk();

    $levels = collect($this->actingAs($this->user)->getJson('/api/inventory/levels')->json('data.rows'));

    expect($levels->firstWhere('sku_code', 'KL-102')['incoming'])->toBe(100);
});

it('books stock in, re-averages cost and closes the order', function (): void {
    $created = draftOrder();
    $this->actingAs($this->user)->postJson("/api/purchasing/orders/{$created['id']}/send");

    $order = PurchaseOrder::query()->with('items')->find($created['id']);
    $received = $order->items->mapWithKeys(fn ($item): array => [$item->id => $item->quantity_ordered])->all();

    $this->actingAs($this->user)
        ->postJson("/api/purchasing/orders/{$created['id']}/receive", ['received' => $received])
        ->assertOk()
        ->assertJsonPath('data.received', 150)
        ->assertJsonPath('data.status', 'received');

    expect($this->ledger->onHand($this->kurta))->toBe(100)
        // Nothing was on hand, so cost becomes the landed price.
        ->and($this->kurta->fresh()->cost_price)->toBe(Money::fromRupees(797.83))
        ->and(StockMovement::query()->where('type', StockMovementType::PurchaseReceipt)->count())->toBe(2);
});

it('handles a partial delivery and leaves the rest outstanding', function (): void {
    $created = draftOrder();
    $this->actingAs($this->user)->postJson("/api/purchasing/orders/{$created['id']}/send");

    $order = PurchaseOrder::query()->with('items')->find($created['id']);
    $kurtaLine = $order->items->firstWhere('sku_id', $this->kurta->id);

    $this->actingAs($this->user)
        ->postJson("/api/purchasing/orders/{$created['id']}/receive", ['received' => [$kurtaLine->id => 60]])
        ->assertOk()
        ->assertJsonPath('data.status', 'partial');

    expect($this->ledger->onHand($this->kurta))->toBe(60)
        ->and($kurtaLine->fresh()->quantityOutstanding())->toBe(40);

    // The rest still shows as incoming, so reorder maths stays right.
    $levels = collect($this->actingAs($this->user)->getJson('/api/inventory/levels')->json('data.rows'));
    expect($levels->firstWhere('sku_code', 'KL-102')['incoming'])->toBe(40);
});

it('refuses to receive more than was ordered', function (): void {
    $created = draftOrder();
    $this->actingAs($this->user)->postJson("/api/purchasing/orders/{$created['id']}/send");

    $order = PurchaseOrder::query()->with('items')->find($created['id']);
    $line = $order->items->firstWhere('sku_id', $this->kurta->id);

    $response = $this->actingAs($this->user)
        ->postJson("/api/purchasing/orders/{$created['id']}/receive", ['received' => [$line->id => 140]]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('only 100 are outstanding');

    // Nothing partial should have landed.
    expect($this->ledger->onHand($this->kurta))->toBe(0);
});

it('will not edit an order the supplier already has', function (): void {
    $created = draftOrder();
    $this->actingAs($this->user)->postJson("/api/purchasing/orders/{$created['id']}/send");

    $this->actingAs($this->user)->putJson("/api/purchasing/orders/{$created['id']}", [
        'items' => [['sku_id' => $this->kurta->id, 'quantity' => 5, 'unit_cost' => 100]],
    ])->assertStatus(422);
});

it('clears incoming when an order is cancelled', function (): void {
    $created = draftOrder();
    $this->actingAs($this->user)->postJson("/api/purchasing/orders/{$created['id']}/send");
    $this->actingAs($this->user)->postJson("/api/purchasing/orders/{$created['id']}/cancel")->assertOk();

    $levels = collect($this->actingAs($this->user)->getJson('/api/inventory/levels')->json('data.rows'));

    expect($levels->firstWhere('sku_code', 'KL-102')['incoming'])->toBe(0);
});

it('suggests a reorder grouped by supplier', function (): void {
    $this->kurta->forceFill(['supplier_id' => $this->supplier->id, 'reorder_point' => 20, 'reorder_quantity' => 120])->save();
    $this->ledger->record($this->kurta, StockMovementType::Opening, 5);

    $groups = $this->actingAs($this->user)->getJson('/api/purchasing/orders/suggestions')->json('data.groups');
    $jaipur = collect($groups)->firstWhere('supplier', 'Jaipur Textiles');

    expect($jaipur['units'])->toBe(120)
        ->and($jaipur['cost'])->toBe(120 * Money::fromRupees(800));
});

it('keeps purchasing away from a role that may only look', function (): void {
    $viewer = $this->userFor($this->tenant, ['catalog.purchase_orders.view'], 'ANALYST');

    $this->actingAs($viewer)->getJson('/api/purchasing/orders')->assertOk();
    $this->actingAs($viewer)->postJson('/api/purchasing/orders', [
        'items' => [['sku_id' => $this->kurta->id, 'quantity' => 1, 'unit_cost' => 10]],
    ])->assertForbidden();
});
