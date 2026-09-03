<?php

declare(strict_types=1);

use App\Domain\Inventory\Services\StockLedger;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Sku;
use App\Models\SkuCostHistory;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Support\Money;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);
    $this->ledger = app(StockLedger::class);

    $this->sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'KL-102',
        'name' => 'Indigo Kurta', 'category' => 'Ethnic Wear', 'cost_price' => Money::fromRupees(780),
        'selling_price' => Money::fromRupees(2299),
    ]);
});

it('adjusts by a delta and records the reason', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    $this->actingAs($this->user)->postJson('/api/inventory/adjust', [
        'sku_id' => $this->sku->id,
        'mode' => 'delta',
        'quantity' => -12,
        'reason' => 'damaged',
        'note' => 'Water damage in transit',
    ])->assertOk()->assertJsonPath('data.balance_after', 38);

    $movement = StockMovement::query()->latest('id')->first();

    // Damage gets its own type, so shrinkage is measurable later.
    expect($movement->type)->toBe(StockMovementType::Damage)
        ->and($movement->reason)->toBe('damaged')
        ->and($movement->note)->toBe('Water damage in transit');
});

it('sets an absolute quantity when the mode is set', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    $this->actingAs($this->user)->postJson('/api/inventory/adjust', [
        'sku_id' => $this->sku->id, 'mode' => 'set', 'quantity' => 42, 'reason' => 'count_correction',
    ])->assertOk()->assertJsonPath('data.balance_after', 42);
});

it('says nothing changed rather than writing an empty movement', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    $this->actingAs($this->user)->postJson('/api/inventory/adjust', [
        'sku_id' => $this->sku->id, 'mode' => 'set', 'quantity' => 50, 'reason' => 'count_correction',
    ])->assertOk()->assertJsonPath('data.changed', false);

    expect(StockMovement::query()->count())->toBe(1);
});

it('demands a reason for every manual adjustment', function (): void {
    $this->actingAs($this->user)->postJson('/api/inventory/adjust', [
        'sku_id' => $this->sku->id, 'mode' => 'delta', 'quantity' => -5,
    ])->assertStatus(422);
});

it('imports opening stock in bulk and names the rows it could not match', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/inventory/bulk-levels', [
        'rows' => [
            ['sku_code' => 'KL-102', 'quantity' => 120],
            ['sku_code' => 'GHOST-1', 'quantity' => 40],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.applied', 1)
        ->assertJsonPath('data.unknown_skus', ['GHOST-1']);

    expect($this->ledger->onHand($this->sku))->toBe(120);
});

it('returns the full ledger for a SKU', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);
    $this->ledger->record($this->sku, StockMovementType::Sale, -8);

    $response = $this->actingAs($this->user)->getJson("/api/inventory/movements/{$this->sku->id}");

    $response->assertOk()->assertJsonPath('data.on_hand', 42);
    expect($response->json('data.rows'))->toHaveCount(2)
        ->and($response->json('data.rows.0.type_label'))->toBe('Sold');
});

it('moves stock between locations without changing the total', function (): void {
    $mumbai = Location::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'manual', 'name' => 'Mumbai', 'is_default' => true]);
    $delhi = Location::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'manual', 'name' => 'Delhi']);

    $this->ledger->record($this->sku, StockMovementType::Opening, 60, $mumbai);

    $this->actingAs($this->user)->postJson('/api/inventory/transfer', [
        'sku_id' => $this->sku->id,
        'from_location_id' => $mumbai->id,
        'to_location_id' => $delhi->id,
        'quantity' => 25,
    ])->assertOk();

    expect($this->ledger->onHand($this->sku, $mumbai))->toBe(35)
        ->and($this->ledger->onHand($this->sku, $delhi))->toBe(25)
        ->and((int) StockMovement::query()->sum('quantity'))->toBe(60);
});

it('refuses a transfer of stock that is not there', function (): void {
    $mumbai = Location::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'manual', 'name' => 'Mumbai', 'is_default' => true]);
    $delhi = Location::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'manual', 'name' => 'Delhi']);

    $this->ledger->record($this->sku, StockMovementType::Opening, 10, $mumbai);

    $response = $this->actingAs($this->user)->postJson('/api/inventory/transfer', [
        'sku_id' => $this->sku->id, 'from_location_id' => $mumbai->id, 'to_location_id' => $delhi->id, 'quantity' => 25,
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('only 10');
});

it('will not deactivate a location that still holds stock', function (): void {
    $mumbai = Location::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'manual', 'name' => 'Mumbai', 'is_default' => true]);
    $this->ledger->record($this->sku, StockMovementType::Opening, 15, $mumbai);

    $this->actingAs($this->user)->deleteJson("/api/inventory/locations/{$mumbai->id}")
        ->assertStatus(422);
});

it('runs a stock count end to end and corrects only what differs', function (): void {
    $other = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'KL-210',
        'name' => 'Cotton Dupatta', 'cost_price' => Money::fromRupees(200),
    ]);

    $this->ledger->record($this->sku, StockMovementType::Opening, 50);
    $this->ledger->record($other, StockMovementType::Opening, 30);

    $created = $this->actingAs($this->user)->postJson('/api/inventory/counts', ['scope' => 'full'])->json('data');
    $sheet = $this->actingAs($this->user)->getJson("/api/inventory/counts/{$created['id']}")->json('data');

    $items = collect($sheet['items']);
    $kurtaItem = $items->firstWhere('sku_code', 'KL-102');
    $dupattaItem = $items->firstWhere('sku_code', 'KL-210');

    expect($kurtaItem['expected_quantity'])->toBe(50);

    $this->actingAs($this->user)->putJson("/api/inventory/counts/{$created['id']}", [
        'items' => [
            ['id' => $kurtaItem['id'], 'counted_quantity' => 46, 'reason' => 'lost'],
            // Dupatta matches, so it should produce no movement at all.
            ['id' => $dupattaItem['id'], 'counted_quantity' => 30],
        ],
    ])->assertOk();

    $this->actingAs($this->user)->postJson("/api/inventory/counts/{$created['id']}/apply")
        ->assertOk()
        ->assertJsonPath('data.corrected', 1)
        ->assertJsonPath('data.units_down', 4)
        ->assertJsonPath('data.uncounted', 0);

    expect($this->ledger->onHand($this->sku))->toBe(46)
        ->and($this->ledger->onHand($other))->toBe(30)
        ->and(StockCount::query()->first()->status)->toBe('applied');
});

it('leaves uncounted lines alone rather than treating blank as zero', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    $created = $this->actingAs($this->user)->postJson('/api/inventory/counts', ['scope' => 'full'])->json('data');

    $this->actingAs($this->user)->postJson("/api/inventory/counts/{$created['id']}/apply")
        ->assertOk()
        ->assertJsonPath('data.uncounted', 1)
        ->assertJsonPath('data.corrected', 0);

    // A blank line is "nobody counted it", not "there are none".
    expect($this->ledger->onHand($this->sku))->toBe(50);
});

it('will not apply the same count twice', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);
    $created = $this->actingAs($this->user)->postJson('/api/inventory/counts', ['scope' => 'full'])->json('data');

    $this->actingAs($this->user)->postJson("/api/inventory/counts/{$created['id']}/apply")->assertOk();
    $this->actingAs($this->user)->postJson("/api/inventory/counts/{$created['id']}/apply")->assertStatus(422);
});

it('flags where managed stock and the sales channel disagree', function (): void {
    Inventory::query()->create([
        'tenant_id' => $this->tenant->id, 'sku_id' => $this->sku->id,
        'location_id' => null, 'source' => 'shopify', 'on_hand' => 12, 'available' => 12,
    ]);

    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    $response = $this->actingAs($this->user)->getJson('/api/inventory/reconciliation');

    $response->assertOk()->assertJsonPath('data.count', 1);

    expect($response->json('data.rows.0.difference'))->toBe(38)
        ->and($response->json('data.rows.0.value_at_risk'))->toBe(38 * Money::fromRupees(780))
        ->and($response->json('data.caveat'))->toContain('never pushed to a sales channel');
});

it('creates a SKU by hand and records its opening cost', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/catalog/skus', [
        'sku_code' => 'KL-999',
        'name' => 'Block Print Saree',
        'category' => 'Ethnic Wear',
        'mrp' => 4999,
        'selling_price' => 3299,
        'cost_price' => 1100,
        'hsn' => '6204',
        'gst_rate' => 5,
    ]);

    $response->assertOk();

    $sku = Sku::query()->where('sku_code', 'KL-999')->first();

    expect($sku->cost_price)->toBe(Money::fromRupees(1100))
        ->and($sku->source)->toBe('manual')
        ->and($sku->tracks_inventory)->toBeTrue()
        // The cost must reach the history, or margin would shift with no trail.
        ->and(SkuCostHistory::query()->where('sku_id', $sku->id)->count())->toBe(1);
});

it('refuses a duplicate SKU code within a tenant', function (): void {
    $this->actingAs($this->user)->postJson('/api/catalog/skus', [
        'sku_code' => 'KL-102', 'name' => 'Clash',
    ])->assertStatus(422);
});

it('archives a SKU instead of deleting its history', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 20);

    $this->actingAs($this->user)->deleteJson("/api/catalog/skus/{$this->sku->id}")->assertOk();

    expect($this->sku->fresh()->is_active)->toBeFalse()
        ->and(StockMovement::query()->where('sku_id', $this->sku->id)->count())->toBe(1);
});

it('keeps stock writes away from a read-only role', function (): void {
    $viewer = $this->userFor($this->tenant, ['catalog.stock.view'], 'ANALYST');

    $this->actingAs($viewer)->getJson('/api/inventory/levels')->assertOk();
    $this->actingAs($viewer)->postJson('/api/inventory/adjust', [
        'sku_id' => $this->sku->id, 'mode' => 'delta', 'quantity' => 5, 'reason' => 'found_extra',
    ])->assertForbidden();
});

it('never shows another tenant stock', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    $other = $this->tenant(['name' => 'Rival Brand']);
    $rival = $this->userFor($other);

    $rows = $this->actingAs($rival)->getJson('/api/inventory/levels')->json('data.rows');

    expect($rows)->toBeEmpty();
});
