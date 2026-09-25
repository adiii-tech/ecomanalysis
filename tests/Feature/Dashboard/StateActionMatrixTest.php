<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = $this->tenant();

    $channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $this->state = function (string $state, int $orders, int $rupees, int $rto = 0) use ($channel): void {
        DB::table('state_daily_rollup')->insert([
            'tenant_id' => $this->tenant->id,
            'date' => CarbonImmutable::now($this->tenant->timezone)->subDays(2)->toDateString(),
            'state' => $state,
            'channel_id' => $channel->id,
            'orders_count' => $orders,
            'gross_sales' => Money::fromRupees($rupees),
            'net_sales' => Money::fromRupees($rupees),
            'margin' => Money::fromRupees((int) round($rupees * 0.3)),
            'rto_count' => $rto,
            'returned_count' => 0,
            'delivered_count' => $orders - $rto,
            'cod_orders' => 0,
            'computed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    $this->matrix = function (): array {
        $user = $this->userFor($this->tenant, ['dashboard.state_action_matrix.view']);

        return $this->actingAs($user)
            ->getJson('/api/dashboard/state-action-matrix?preset=last_30_days')
            ->assertOk()
            ->json('data');
    };
});

it('puts each state in the quadrant its volume and RTO earn', function (): void {
    // Median net sales lands at ₹30,000, so ₹50k/₹40k are high volume and ₹20k/₹10k are not.
    ($this->state)('Maharashtra', orders: 100, rupees: 50000, rto: 2);
    ($this->state)('Uttar Pradesh', orders: 100, rupees: 40000, rto: 40);
    ($this->state)('Kerala', orders: 20, rupees: 20000, rto: 1);
    ($this->state)('Bihar', orders: 20, rupees: 10000, rto: 9);

    $quadrants = collect(($this->matrix)()['rows'])->pluck('quadrant', 'state')->all();

    expect($quadrants)->toBe([
        'Maharashtra' => 'scale',
        'Uttar Pradesh' => 'fix',
        'Kerala' => 'grow',
        'Bihar' => 'restrict',
    ]);
});

it('reports the median the quadrant split is drawn against', function (): void {
    ($this->state)('Maharashtra', orders: 10, rupees: 5000);
    ($this->state)('Karnataka', orders: 10, rupees: 3000);
    ($this->state)('Delhi', orders: 10, rupees: 1000);

    expect(($this->matrix)()['median_net_sales'])->toBe(Money::fromRupees(3000));
});

it('says how many states it left out for being too small', function (): void {
    ($this->state)('Maharashtra', orders: 40, rupees: 50000);
    ($this->state)('Karnataka', orders: 30, rupees: 30000);
    ($this->state)('Goa', orders: 4, rupees: 9000);
    ($this->state)('Sikkim', orders: 1, rupees: 2000);

    $data = ($this->matrix)();

    expect(collect($data['rows'])->pluck('state')->all())->toBe(['Maharashtra', 'Karnataka'])
        ->and($data['excluded_states'])->toBe(2)
        ->and($data['caveat'])->toContain('2 states left out');
});

it('keeps quiet when nothing was excluded', function (): void {
    ($this->state)('Maharashtra', orders: 40, rupees: 50000);
    ($this->state)('Karnataka', orders: 30, rupees: 30000);

    $data = ($this->matrix)();

    expect($data['excluded_states'])->toBe(0)
        ->and($data['caveat'])->toBeNull();
});

it('does not let one small state fake an RTO problem', function (): void {
    // 1 RTO out of 2 orders is 50%, which would otherwise dwarf every real state.
    ($this->state)('Maharashtra', orders: 200, rupees: 50000, rto: 4);
    ($this->state)('Ladakh', orders: 2, rupees: 1000, rto: 1);

    $data = ($this->matrix)();

    expect(collect($data['rows'])->pluck('state')->all())->toBe(['Maharashtra'])
        ->and($data['caveat'])->toContain('1 state left out');
});

it('returns an empty matrix rather than an error when no state qualifies', function (): void {
    ($this->state)('Goa', orders: 2, rupees: 5000);

    $data = ($this->matrix)();

    expect($data['rows'])->toBe([])
        ->and($data['median_net_sales'])->toBe(0)
        ->and($data['excluded_states'])->toBe(1);
});
