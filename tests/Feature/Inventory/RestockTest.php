<?php

declare(strict_types=1);

use App\Models\Sku;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant, ['catalog.restock.view']);
    $this->today = CarbonImmutable::now($this->tenant->timezone);

    $this->sku = function (string $code, int $cost, int $sellingPrice = 400): Sku {
        return Sku::query()->create([
            'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => 'v-'.$code,
            'sku_code' => $code, 'name' => $code.' product',
            'cost_price' => Money::fromRupees($cost), 'selling_price' => Money::fromRupees($sellingPrice),
        ]);
    };

    $this->stock = fn (Sku $sku, int $available, int $incoming = 0): bool => DB::table('inventory')->insert([
        'tenant_id' => $this->tenant->id, 'sku_id' => $sku->id, 'location_id' => null, 'source' => 'shopify',
        'on_hand' => $available, 'available' => $available, 'reserved' => 0, 'incoming' => $incoming,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->sold = fn (Sku $sku, int $daysAgo, int $units, int $revenue = 0, int $returned = 0): bool => DB::table('sku_daily_rollup')->insert([
        'tenant_id' => $this->tenant->id, 'sku_id' => $sku->id, 'channel_id' => null,
        'date' => $this->today->subDays($daysAgo)->toDateString(),
        'units_sold' => $units, 'orders_count' => $units, 'returned_units' => $returned,
        'gross_sales' => $revenue, 'net_sales' => $revenue,
        'computed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->rows = function (array $params = []): Collection {
        $query = http_build_query([...['window' => 90, 'lead' => 21, 'safety' => 14, 'target' => 60], ...$params]);

        return collect(
            $this->actingAs($this->user)->getJson('/api/restock?'.$query)->assertOk()->json('data.rows'),
        )->keyBy('sku_code');
    };
});

it('turns 90 days of sales into a velocity, a cover and a quantity to buy', function (): void {
    $fast = ($this->sku)('FAST-1', 100);
    ($this->stock)($fast, 10);
    // 90 units across a 90-day window, still selling today: one a day.
    ($this->sold)($fast, 89, 1, Money::fromRupees(400));
    ($this->sold)($fast, 0, 89, Money::fromRupees(35600));

    $row = ($this->rows)()['FAST-1'];

    // JSON hands a whole number back as an int, so the value is what matters here.
    expect((float) $row['velocity'])->toBe(1.0)
        ->and((float) $row['cover_days'])->toBe(10.0)
        ->and($row['bucket'])->toBe('reorder')
        // 60 days of cover wanted, 10 in stock, nothing incoming.
        ->and($row['suggested_qty'])->toBe(50)
        ->and($row['order_value'])->toBe(Money::fromRupees(5000));
});

it('counts stock nobody has bought in months as dead money', function (): void {
    // Sales are anchored to the store's last selling day, so the store has to be alive.
    ($this->sold)(($this->sku)('LIVE-1', 50), 0, 4, Money::fromRupees(1600));

    $dead = ($this->sku)('DEAD-1', 200);
    ($this->stock)($dead, 5);
    ($this->sold)($dead, 199, 3, Money::fromRupees(1200));

    $row = ($this->rows)()['DEAD-1'];

    expect($row['bucket'])->toBe('dead')
        ->and($row['blocked_value'])->toBe(Money::fromRupees(1000))
        ->and($row['suggested_qty'])->toBe(0);
});

it('keeps combo SKUs out of the money and the reorder list', function (): void {
    $combo = ($this->sku)('GIFT-STACK-1', 100);
    ($this->stock)($combo, 12);
    ($this->sold)($combo, 1, 20);

    $row = ($this->rows)(['exclude' => 'stack, combo'])['GIFT-STACK-1'];

    expect($row['bucket'])->toBe('excluded')
        ->and($row['stock_value'])->toBe(0)
        ->and($row['suggested_qty'])->toBe(0);
});

it('adds up the money stuck, the money to spend and where it sits', function (): void {
    $fast = ($this->sku)('FAST-1', 100);
    ($this->stock)($fast, 10);
    ($this->sold)($fast, 89, 1, Money::fromRupees(400));
    ($this->sold)($fast, 0, 89, Money::fromRupees(35600));

    $dead = ($this->sku)('DEAD-1', 200);
    ($this->stock)($dead, 5);
    ($this->sold)($dead, 199, 3, Money::fromRupees(1200));

    $data = $this->actingAs($this->user)
        ->getJson('/api/restock?window=90&lead=21&safety=14&target=60')
        ->assertOk()
        ->json('data');

    expect($data['summary']['stock_value'])->toBe(Money::fromRupees(2000))
        ->and($data['summary']['dead_value'])->toBe(Money::fromRupees(1000))
        ->and($data['summary']['spend_now'])->toBe(Money::fromRupees(5000))
        ->and($data['summary']['working_value'])->toBe(Money::fromRupees(1000))
        ->and(collect($data['buckets'])->firstWhere('bucket', 'reorder')['skus'])->toBe(1)
        ->and(collect($data['buckets'])->firstWhere('bucket', 'dead')['skus'])->toBe(1)
        ->and($data['health'])->not->toBeEmpty();
});

it('rates a stocked-out seller on the days it was actually on the shelf', function (): void {
    // A live store with a full 90 days of history behind it.
    $live = ($this->sku)('LIVE-1', 50);
    ($this->sold)($live, 89, 1, Money::fromRupees(400));
    ($this->sold)($live, 0, 4, Money::fromRupees(1600));

    $out = ($this->sku)('OUT-1', 100);
    ($this->stock)($out, 0);
    // Sold 30 units, then ran dry 60 days ago and has sold nothing since.
    ($this->sold)($out, 60, 30, Money::fromRupees(12000));

    $row = ($this->rows)()['OUT-1'];

    expect($row['bucket'])->toBe('reorder')
        ->and($row['velocity_adjusted'])->toBeTrue()
        // 30 units over the 30 days it was on the shelf, not over the full 90.
        ->and((float) $row['velocity'])->toBe(1.0)
        ->and($row['suggested_qty'])->toBe(60);
});

it('survives a day where more came back than went out', function (): void {
    $churn = ($this->sku)('CHURN-1', 100);
    ($this->stock)($churn, 4);
    // Yesterday's returns outnumber yesterday's sales; the columns are unsigned,
    // so netting them without a cast used to blow up the whole page.
    ($this->sold)($churn, 30, 10, Money::fromRupees(4000));
    ($this->sold)($churn, 1, 1, Money::fromRupees(400), 3);

    $row = ($this->rows)()['CHURN-1'];

    expect($row['units_window'])->toBe(8)
        ->and($row['returns_window'])->toBe(3);
});

it('will not show the restock desk to a role without the permission', function (): void {
    $analyst = $this->userFor($this->tenant, ['catalog.stock.view']);

    $this->actingAs($analyst)->getJson('/api/restock')->assertForbidden();
});

it('hands the drawer twelve months of history, netted the same way the table is', function (): void {
    $sku = ($this->sku)('HIST-1', 100);
    ($this->stock)($sku, 10);
    ($this->sold)($sku, 0, 12, Money::fromRupees(4800), 2);
    ($this->sold)($sku, 200, 5, Money::fromRupees(2000));

    $data = $this->actingAs($this->user)->getJson('/api/restock/sku/'.$sku->id)->assertOk()->json('data');

    expect($data['months'])->toHaveCount(12)
        // 12 sold less 2 returned on the anchor day.
        ->and($data['units_30'])->toBe(10)
        ->and($data['lifetime_units'])->toBe(15)
        ->and($data['returns_lifetime'])->toBe(2)
        ->and(collect($data['months'])->last()['units'])->toBe(10);

    $gross = $this->actingAs($this->user)->getJson('/api/restock/sku/'.$sku->id.'?returns=gross')->assertOk()->json('data');

    expect($gross['units_30'])->toBe(12)
        ->and($gross['lifetime_units'])->toBe(17);
});

it('floors a month that took back more than it sold', function (): void {
    $sku = ($this->sku)('REFUND-1', 100);
    ($this->sold)($sku, 0, 1, Money::fromRupees(400), 6);

    $data = $this->actingAs($this->user)->getJson('/api/restock/sku/'.$sku->id)->assertOk()->json('data');

    // A month of net returns is an empty bar, never a negative one.
    expect(collect($data['months'])->pluck('units')->every(fn (int $units): bool => $units >= 0))->toBeTrue();
});

it('keeps the history endpoint behind the restock permission', function (): void {
    $sku = ($this->sku)('LOCKED-1', 100);
    $analyst = $this->userFor($this->tenant, ['catalog.stock.view']);

    $this->actingAs($analyst)->getJson('/api/restock/sku/'.$sku->id)->assertForbidden();
});

it('says a freshly added product is new rather than quietly calling it dead', function (): void {
    ($this->sold)(($this->sku)('LIVE-1', 50), 0, 4, Money::fromRupees(1600));

    $fresh = ($this->sku)('FRESH-1', 100);
    ($this->stock)($fresh, 20);

    $data = $this->actingAs($this->user)->getJson('/api/restock?window=90&new_days=30')->assertOk()->json('data');

    expect(collect($data['rows'])->firstWhere('sku_code', 'FRESH-1')['bucket'])->toBe('new')
        ->and(collect($data['health'])->pluck('text')->join(' '))->toContain('read as New rather than Dead');
});

it('owns up to sales booked against SKUs that are no longer active', function (): void {
    $live = ($this->sku)('LIVE-1', 50);
    ($this->stock)($live, 10);
    ($this->sold)($live, 0, 10, Money::fromRupees(4000));

    $archived = ($this->sku)('GONE-1', 50);
    $archived->update(['is_active' => false]);
    ($this->sold)($archived, 1, 40, Money::fromRupees(16000));

    $data = $this->actingAs($this->user)->getJson('/api/restock?window=90')->assertOk()->json('data');

    expect(collect($data['rows'])->pluck('sku_code'))->not->toContain('GONE-1')
        ->and(collect($data['health'])->pluck('text')->join(' '))->toContain('40 units sold against archived or deleted SKUs');
});
