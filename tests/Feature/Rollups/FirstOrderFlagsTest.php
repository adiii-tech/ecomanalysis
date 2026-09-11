<?php

declare(strict_types=1);

use App\Domain\Rollups\Actions\RebuildFirstOrderFlags;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = $this->tenant();

    $this->insertOrder = fn (int $tenantId, ?int $customerId, string $externalId, string $placedAt, bool $isFirst): int => DB::table('orders')->insertGetId([
        'tenant_id' => $tenantId,
        'customer_id' => $customerId,
        'source' => 'shopify',
        'external_id' => $externalId,
        'placed_at' => $placedAt,
        'is_first_order' => $isFirst,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('flags only each customer earliest order as their first, whatever order they arrived in', function (): void {
    $repeat = Customer::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => 'c-repeat']);
    $single = Customer::query()->create(['tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => 'c-single']);

    // The newer order landed first, so both of the repeat buyer's orders claim to be first.
    $newer = ($this->insertOrder)($this->tenant->id, $repeat->id, 'o-newer', '2026-08-01 10:00:00', true);
    $older = ($this->insertOrder)($this->tenant->id, $repeat->id, 'o-older', '2026-03-01 10:00:00', true);
    $only = ($this->insertOrder)($this->tenant->id, $single->id, 'o-only', '2026-05-01 10:00:00', false);
    $guest = ($this->insertOrder)($this->tenant->id, null, 'o-guest', '2026-05-02 10:00:00', false);

    app(RebuildFirstOrderFlags::class)->handle($this->tenant);

    $flags = DB::table('orders')->pluck('is_first_order', 'id')->map(fn (mixed $flag): bool => (bool) $flag);

    expect($flags[$older])->toBeTrue()
        ->and($flags[$newer])->toBeFalse()
        ->and($flags[$only])->toBeTrue()
        ->and($flags[$guest])->toBeFalse();
});

it('never rewrites another tenant orders', function (): void {
    $other = $this->tenant();
    $customer = Customer::query()->create(['tenant_id' => $other->id, 'source' => 'shopify', 'external_id' => 'c-other']);
    $orderId = ($this->insertOrder)($other->id, $customer->id, 'o-other', '2026-05-01 10:00:00', false);

    app(RebuildFirstOrderFlags::class)->handle($this->tenant);

    expect((bool) DB::table('orders')->where('id', $orderId)->value('is_first_order'))->toBeFalse();
});
