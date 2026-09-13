<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant, ['dashboard.kpi_strip.view']);

    $channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    // Ten orders placed for ₹10,000: two cancelled, one returned, one RTO — six stayed sold for ₹4,800.
    DB::table('daily_metrics_rollup')->insert([
        'tenant_id' => $this->tenant->id,
        'date' => CarbonImmutable::now($this->tenant->timezone)->subDays(2)->toDateString(),
        'channel_id' => $channel->id,
        'payment_mode' => 'prepaid',
        'orders_count' => 10,
        'cancelled_orders' => 2,
        'invoiced_orders' => 8,
        'returned_orders' => 1,
        'rto_orders' => 1,
        'gross_sales' => Money::fromRupees(10000),
        'net_sales' => Money::fromRupees(4800),
        'computed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->cards = fn (): Collection => collect(
        $this->actingAs($this->user)->getJson('/api/dashboard/kpis?preset=last_30_days')->assertOk()->json('data'),
    )->keyBy('key');
});

it('splits AOV into what was placed and what stayed sold', function (): void {
    $cards = ($this->cards)();

    expect((int) $cards['gross_aov']['value'])->toBe(Money::fromRupees(1000))
        ->and((int) $cards['net_aov']['value'])->toBe(Money::fromRupees(800));
});

it('keeps both AOVs on the strip with their own comparison and sparkline', function (): void {
    $cards = ($this->cards)();

    expect($cards)->toHaveKeys(['net_aov', 'gross_aov'])
        ->and($cards['net_aov']['label'])->toBe('Net AOV')
        ->and($cards['gross_aov']['label'])->toBe('Gross AOV')
        ->and($cards['net_aov']['format'])->toBe('currency')
        ->and($cards['net_aov']['sparkline'])->not->toBeEmpty();
});

it('shows zero rather than dividing by no orders', function (): void {
    DB::table('daily_metrics_rollup')->delete();

    $cards = ($this->cards)();

    expect((float) $cards['net_aov']['value'])->toBe(0.0)
        ->and((float) $cards['gross_aov']['value'])->toBe(0.0);
});
