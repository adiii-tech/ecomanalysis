<?php

declare(strict_types=1);

use App\Domain\Alerts\Services\MetricResolver;
use App\Domain\Inventory\Actions\RecalculateReservations;
use App\Domain\Inventory\Services\BatchLedger;
use App\Domain\Inventory\Services\BundleAvailability;
use App\Domain\Inventory\Services\StockLedger;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Models\SkuComponent;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Support\Money;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);
    $this->ledger = app(StockLedger::class);
    $this->batches = app(BatchLedger::class);

    $this->serum = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'BEA-001',
        'name' => 'Vitamin C Serum', 'cost_price' => Money::fromRupees(300),
        'selling_price' => Money::fromRupees(899), 'tracks_batches' => true, 'shelf_life_days' => 365,
    ]);
});

it('takes the closest-to-expiry batch first, not the oldest received', function (): void {
    // Received later, but expires sooner — it must go first.
    $this->batches->receive($this->serum, 'B-JAN', 100, Money::fromRupees(300), null, now()->addDays(200)->toDateString());
    $this->batches->receive($this->serum, 'B-FEB', 100, Money::fromRupees(320), null, now()->addDays(60)->toDateString());

    $taken = $this->batches->consume($this->serum, 120);

    expect($taken)->toHaveCount(2)
        ->and($taken[0]['batch_code'])->toBe('B-FEB')
        ->and($taken[0]['quantity'])->toBe(100)
        ->and($taken[1]['batch_code'])->toBe('B-JAN')
        ->and($taken[1]['quantity'])->toBe(20);
});

it('falls back to oldest-received when batches have no expiry', function (): void {
    $undated = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'ACC-001',
        'name' => 'Cotton Pouch', 'cost_price' => Money::fromRupees(40),
    ]);

    $this->batches->receive($undated, 'FIRST', 30, Money::fromRupees(40));
    $this->batches->receive($undated, 'SECOND', 30, Money::fromRupees(45));

    $taken = $this->batches->consume($undated, 40);

    expect($taken[0]['batch_code'])->toBe('FIRST')
        ->and($taken[1]['batch_code'])->toBe('SECOND');
});

it('refuses to consume more than the batches hold', function (): void {
    $this->batches->receive($this->serum, 'B-JAN', 10, Money::fromRupees(300));

    expect(fn () => $this->batches->consume($this->serum, 25))
        ->toThrow(RuntimeException::class, 'cannot take 25');

    // Nothing partial should have been taken.
    expect((int) StockBatch::query()->sum('quantity'))->toBe(10);
});

it('values stock at what each batch actually cost, not at an average', function (): void {
    $this->batches->receive($this->serum, 'CHEAP', 100, Money::fromRupees(200));
    $this->batches->receive($this->serum, 'DEAR', 100, Money::fromRupees(400));

    // 100 × ₹200 + 100 × ₹400 = ₹60,000.
    expect($this->batches->valuation())->toBe(Money::fromRupees(60000));

    $this->batches->consume($this->serum, 100);

    // The cheap batch went first, so what is left is the dear one.
    expect($this->batches->valuation())->toBe(Money::fromRupees(40000));
});

it('derives an expiry from the SKU shelf life when a receipt has no date', function (): void {
    $batch = $this->batches->receive($this->serum, 'NO-DATE', 20, Money::fromRupees(300));

    expect($batch->expires_on?->toDateString())->toBe(now()->addDays(365)->toDateString());
});

it('books a batch in through the API and moves the balance with it', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/inventory/batches', [
        'sku_id' => $this->serum->id,
        'batch_code' => 'B-2609',
        'quantity' => 80,
        'unit_cost' => 310,
        'expires_on' => now()->addDays(120)->toDateString(),
    ]);

    $response->assertOk()->assertJsonPath('data.balance_after', 80);

    // Batch and overall balance must agree, or one of them is lying.
    expect($this->ledger->onHand($this->serum))->toBe(80)
        ->and((int) StockBatch::query()->where('batch_code', 'B-2609')->value('quantity'))->toBe(80);
});

it('lists expiring batches with the money at risk', function (): void {
    $this->batches->receive($this->serum, 'SOON', 50, Money::fromRupees(300), null, now()->addDays(20)->toDateString());
    $this->batches->receive($this->serum, 'LATER', 50, Money::fromRupees(300), null, now()->addDays(300)->toDateString());

    $response = $this->actingAs($this->user)->getJson('/api/inventory/batches/expiring?within_days=60');

    $response->assertOk();

    expect($response->json('data.rows'))->toHaveCount(1)
        ->and($response->json('data.rows.0.batch_code'))->toBe('SOON')
        ->and($response->json('data.value_at_risk'))->toBe(50 * Money::fromRupees(300))
        ->and($response->json('meta.verdict.status'))->toBe('watch');
});

it('calls out batches that have already expired', function (): void {
    $batch = $this->batches->receive($this->serum, 'GONE', 40, Money::fromRupees(300));
    $batch->forceFill(['expires_on' => now()->subDays(5)->toDateString()])->save();

    $response = $this->actingAs($this->user)->getJson('/api/inventory/batches/expiring');

    expect($response->json('meta.verdict.status'))->toBe('bad')
        ->and($response->json('data.expired_units'))->toBe(40);
});

it('writes off an expired batch and records the loss', function (): void {
    // Book two batches in through the API so batch quantities and the overall
    // balance start out agreeing.
    foreach ([['GOOD', 40, 200], ['GONE', 40, 5]] as [$code, $qty, $days]) {
        $this->actingAs($this->user)->postJson('/api/inventory/batches', [
            'sku_id' => $this->serum->id, 'batch_code' => $code, 'quantity' => $qty,
            'unit_cost' => 300, 'expires_on' => now()->addDays($days)->toDateString(),
        ])->assertOk();
    }

    expect($this->ledger->onHand($this->serum))->toBe(80);

    $batch = StockBatch::query()->where('batch_code', 'GONE')->first();
    $batch->forceFill(['expires_on' => now()->subDay()->toDateString()])->save();

    $response = $this->actingAs($this->user)->postJson("/api/inventory/batches/{$batch->id}/write-off");

    $response->assertOk();
    expect($response->json('message'))->toContain('₹12,000');

    // The expired batch is emptied and the balance falls with it.
    expect($batch->fresh()->quantity)->toBe(0)
        ->and($this->ledger->onHand($this->serum))->toBe(40)
        ->and(StockMovement::query()->where('reason', 'expired')->count())->toBe(1);
});

it('makes a bundle only as available as its scarcest component', function (): void {
    $kurta = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'ETH-001',
        'name' => 'Kurta', 'cost_price' => Money::fromRupees(700),
    ]);
    $dupatta = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'ETH-002',
        'name' => 'Dupatta', 'cost_price' => Money::fromRupees(200),
    ]);
    $combo = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'COMBO-1',
        'name' => 'Festive Set', 'cost_price' => Money::fromRupees(900),
    ]);

    $this->ledger->record($kurta, StockMovementType::Opening, 50);
    $this->ledger->record($dupatta, StockMovementType::Opening, 14);

    app(BundleAvailability::class)->setComponents($combo, [
        ['sku_id' => $kurta->id, 'quantity' => 1],
        ['sku_id' => $dupatta->id, 'quantity' => 2],
    ]);

    $result = app(BundleAvailability::class)->forBundle($combo->fresh());

    // 14 dupattas at 2 per set caps the bundle at 7, not 50.
    expect($result['buildable'])->toBe(7)
        ->and($result['limiting_sku'])->toBe('ETH-002')
        ->and($result['components'])->toHaveCount(2);
});

it('stops a bundle from carrying stock of its own', function (): void {
    $part = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'P-1', 'name' => 'Part',
    ]);
    $combo = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'C-1', 'name' => 'Combo',
    ]);

    app(BundleAvailability::class)->setComponents($combo, [['sku_id' => $part->id, 'quantity' => 1]]);

    expect($combo->fresh()->tracks_inventory)->toBeFalse()
        ->and($combo->fresh()->is_combo)->toBeTrue();

    // And so it cannot be given a balance directly.
    expect(fn () => $this->ledger->record($combo->fresh(), StockMovementType::Opening, 5))
        ->toThrow(RuntimeException::class, 'not stock-tracked');
});

it('refuses to let a bundle contain itself', function (): void {
    $combo = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'C-2', 'name' => 'Combo',
    ]);

    app(BundleAvailability::class)->setComponents($combo, [['sku_id' => $combo->id, 'quantity' => 1]]);

    expect(SkuComponent::query()->count())->toBe(0);
});

it('saves a bundle recipe through the API', function (): void {
    $part = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'P-9', 'name' => 'Part',
    ]);
    $combo = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'C-9', 'name' => 'Combo',
    ]);

    $this->ledger->record($part, StockMovementType::Opening, 30);

    $this->actingAs($this->user)->putJson("/api/inventory/bundles/{$combo->id}", [
        'components' => [['sku_id' => $part->id, 'quantity' => 3]],
    ])->assertOk()->assertJsonPath('data.buildable', 10);
});

it('reserves stock for orders that have not shipped', function (): void {
    $sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'ETH-050', 'name' => 'Kurta',
    ]);

    $this->ledger->record($sku, StockMovementType::Opening, 60);

    foreach ([OrderStatus::Placed, OrderStatus::Confirmed, OrderStatus::Delivered] as $index => $status) {
        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'o-'.$index,
            'order_number' => '#'.$index, 'placed_at' => CarbonImmutable::now(),
            'status' => $status, 'payment_mode' => PaymentMode::Prepaid,
        ]);

        OrderItem::query()->create([
            'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $sku->id,
            'sku_code' => $sku->sku_code, 'qty' => 5,
        ]);
    }

    $result = app(RecalculateReservations::class)->handle();

    // Only the two open orders count; the delivered one has already gone.
    expect($result['reserved_units'])->toBe(10);

    $row = Inventory::query()->where('sku_id', $sku->id)->where('source', 'manual')->first();

    expect($row->reserved)->toBe(10)
        ->and($row->available)->toBe(50)
        ->and($row->on_hand)->toBe(60);
});

it('releases a reservation once the order ships', function (): void {
    $sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'manual', 'sku_code' => 'ETH-051', 'name' => 'Kurta',
    ]);

    $this->ledger->record($sku, StockMovementType::Opening, 20);

    $order = Order::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'o-x',
        'order_number' => '#x', 'placed_at' => CarbonImmutable::now(),
        'status' => OrderStatus::Placed, 'payment_mode' => PaymentMode::Prepaid,
    ]);

    OrderItem::query()->create([
        'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $sku->id,
        'sku_code' => $sku->sku_code, 'qty' => 8,
    ]);

    app(RecalculateReservations::class)->handle();
    expect(Inventory::query()->where('sku_id', $sku->id)->value('reserved'))->toBe(8);

    $order->forceFill(['status' => OrderStatus::Shipped])->save();
    app(RecalculateReservations::class)->handle();

    // Derived from open orders, so a missed event cannot leave it stuck.
    expect(Inventory::query()->where('sku_id', $sku->id)->value('reserved'))->toBe(0)
        ->and(Inventory::query()->where('sku_id', $sku->id)->value('available'))->toBe(20);
});

it('exposes stock and expiry as alert metrics', function (): void {
    $catalogue = MetricResolver::catalogue();

    expect($catalogue)->toHaveKey('stock_on_hand')
        ->and($catalogue)->toHaveKey('expiring_stock_days')
        ->and($catalogue['expiring_stock_days']['dimension'])->toBe('batch');
});

it('reports days left on each batch for the alert engine', function (): void {
    $this->batches->receive($this->serum, 'SOON', 30, Money::fromRupees(300), null, now()->addDays(12)->toDateString());

    $readings = app(MetricResolver::class)->resolve('expiring_stock_days', 7);

    expect($readings)->toHaveCount(1)
        ->and($readings[0]['dimension'])->toContain('SOON')
        ->and((int) $readings[0]['value'])->toBe(12)
        ->and($readings[0]['context']['units'])->toBe(30);
});
