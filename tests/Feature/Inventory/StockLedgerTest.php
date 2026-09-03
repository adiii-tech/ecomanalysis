<?php

declare(strict_types=1);

use App\Domain\Inventory\Services\CostAverager;
use App\Domain\Inventory\Services\StockLedger;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Support\Money;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);

    $this->sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'KL-102',
        'name' => 'Indigo Kurta', 'mrp' => Money::fromRupees(3499),
        'selling_price' => Money::fromRupees(2299), 'cost_price' => Money::fromRupees(780),
    ]);

    $this->ledger = app(StockLedger::class);
});

it('creates a default warehouse the first time stock is posted', function (): void {
    expect(Location::query()->count())->toBe(0);

    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    $location = Location::query()->first();

    expect($location->name)->toBe('Main warehouse')
        ->and($location->is_default)->toBeTrue();
});

it('moves the balance and writes the ledger together', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);
    $this->ledger->record($this->sku, StockMovementType::Sale, -12);
    $movement = $this->ledger->record($this->sku, StockMovementType::ReturnIn, 3);

    $row = Inventory::query()->where('sku_id', $this->sku->id)->where('source', 'manual')->first();

    expect($row->on_hand)->toBe(41)
        ->and($row->available)->toBe(41)
        ->and($movement->balance_after)->toBe(41)
        // The ledger must add up to the balance, every time.
        ->and((int) StockMovement::query()->where('sku_id', $this->sku->id)->sum('quantity'))->toBe(41);
});

it('records the balance after each movement so history can be replayed', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 100);
    $this->ledger->record($this->sku, StockMovementType::Sale, -30);
    $this->ledger->record($this->sku, StockMovementType::Damage, -5);

    expect(StockMovement::query()->orderBy('id')->pluck('balance_after')->all())->toBe([100, 70, 65]);
});

it('refuses a zero movement instead of writing a meaningless row', function (): void {
    expect(fn () => $this->ledger->record($this->sku, StockMovementType::Adjustment, 0))
        ->toThrow(RuntimeException::class);

    expect(StockMovement::query()->count())->toBe(0);
});

it('refuses to move stock on a SKU that is not stock-tracked', function (): void {
    $this->sku->forceFill(['tracks_inventory' => false])->save();

    expect(fn () => $this->ledger->record($this->sku->fresh(), StockMovementType::Opening, 10))
        ->toThrow(RuntimeException::class, 'not stock-tracked');
});

it('sets an absolute level and records only the difference', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 40);

    $movement = $this->ledger->setLevel($this->sku, 34, StockMovementType::CountCorrection);

    expect($movement->quantity)->toBe(-6)
        ->and($movement->balance_after)->toBe(34);
});

it('writes nothing when a count matches what the system believed', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 40);

    expect($this->ledger->setLevel($this->sku, 40, StockMovementType::CountCorrection))->toBeNull()
        ->and(StockMovement::query()->count())->toBe(1);
});

it('keeps reserved stock out of available without touching on hand', function (): void {
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);
    $this->ledger->reserve($this->sku, 8);

    $row = Inventory::query()->where('sku_id', $this->sku->id)->where('source', 'manual')->first();

    expect($row->on_hand)->toBe(50)
        ->and($row->reserved)->toBe(8)
        ->and($row->available)->toBe(42);
});

it('keeps managed stock separate from what a sales channel reports', function (): void {
    Inventory::query()->create([
        'tenant_id' => $this->tenant->id, 'sku_id' => $this->sku->id,
        'location_id' => null, 'source' => 'shopify', 'on_hand' => 12, 'available' => 12,
    ]);

    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    // Both survive: the channel number is what customers buy against, ours is
    // what we actually hold.
    expect(Inventory::query()->where('source', 'shopify')->value('on_hand'))->toBe(12)
        ->and($this->ledger->onHand($this->sku))->toBe(50);
});

it('tracks each location separately', function (): void {
    $mumbai = Location::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'manual', 'name' => 'Mumbai', 'is_default' => true]);
    $delhi = Location::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'manual', 'name' => 'Delhi']);

    $this->ledger->record($this->sku, StockMovementType::Opening, 30, $mumbai);
    $this->ledger->record($this->sku, StockMovementType::Opening, 20, $delhi);

    expect($this->ledger->onHand($this->sku, $mumbai))->toBe(30)
        ->and($this->ledger->onHand($this->sku, $delhi))->toBe(20);
});

it('weights cost against stock already on the shelf', function (): void {
    // 50 on hand at ₹100, receiving 100 at ₹80 → ₹86.67, not ₹80.
    $this->sku->forceFill(['cost_price' => Money::fromRupees(100)])->save();
    $this->ledger->record($this->sku, StockMovementType::Opening, 50);

    $newCost = app(CostAverager::class)->applyReceipt($this->sku->fresh(), 100, Money::fromRupees(80));

    expect($newCost)->toBe(Money::fromRupees(86.67))
        ->and($this->sku->fresh()->cost_price)->toBe(Money::fromRupees(86.67));
});

it('takes the receipt cost directly when nothing is on hand', function (): void {
    $newCost = app(CostAverager::class)->applyReceipt($this->sku, 100, Money::fromRupees(640));

    expect($newCost)->toBe(Money::fromRupees(640));
});

it('spreads freight across lines by value, not evenly', function (): void {
    $landed = app(CostAverager::class)->allocateLandedCost([
        ['sku_id' => 1, 'quantity' => 10, 'unit_cost' => Money::fromRupees(100)],
        ['sku_id' => 2, 'quantity' => 10, 'unit_cost' => Money::fromRupees(400)],
    ], Money::fromRupees(1000));

    // The ₹4,000 line carries 80% of the freight, so ₹800 over 10 units.
    expect($landed[0])->toBe(Money::fromRupees(120))
        ->and($landed[1])->toBe(Money::fromRupees(480));
});
