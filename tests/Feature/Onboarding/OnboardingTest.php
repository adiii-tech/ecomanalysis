<?php

declare(strict_types=1);

use App\Models\Benchmark;
use App\Models\Connector;
use App\Models\CostSetting;
use App\Models\SyncRun;
use App\Support\Money;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);
});

it('reports every step as incomplete for a fresh tenant', function (): void {
    $response = $this->actingAs($this->user)->getJson('/api/onboarding');

    $response->assertOk();

    $steps = collect($response->json('data.steps'))->keyBy('key');

    expect($steps['connect']['complete'])->toBeFalse()
        ->and($steps['sync']['complete'])->toBeFalse()
        ->and($steps['sync']['blocked_by'])->toBe('connect')
        ->and($response->json('data.progress.done'))->toBe(0);
});

it('marks the connect step from a real connector, not a flag', function (): void {
    Connector::query()->create([
        'tenant_id' => $this->tenant->id, 'connector_id' => 'shopify', 'status' => 'connected', 'credentials' => [],
    ]);

    $steps = collect($this->actingAs($this->user)->getJson('/api/onboarding')->json('data.steps'))->keyBy('key');

    expect($steps['connect']['complete'])->toBeTrue()
        ->and($steps['connect']['evidence'])->toContain('Shopify')
        ->and($steps['sync']['blocked_by'])->toBeNull();
});

it('marks the sync step only once a sync has actually succeeded', function (): void {
    Connector::query()->create([
        'tenant_id' => $this->tenant->id, 'connector_id' => 'shopify', 'status' => 'connected', 'credentials' => [],
    ]);

    SyncRun::query()->create([
        'tenant_id' => $this->tenant->id, 'connector_id' => 'shopify', 'entity' => 'orders',
        'status' => 'failed', 'started_at' => now(),
    ]);

    $steps = collect($this->actingAs($this->user)->getJson('/api/onboarding')->json('data.steps'))->keyBy('key');
    expect($steps['sync']['complete'])->toBeFalse();

    SyncRun::query()->create([
        'tenant_id' => $this->tenant->id, 'connector_id' => 'shopify', 'entity' => 'orders',
        'status' => 'success', 'started_at' => now(), 'finished_at' => now(),
    ]);

    $steps = collect($this->actingAs($this->user)->getJson('/api/onboarding')->json('data.steps'))->keyBy('key');
    expect($steps['sync']['complete'])->toBeTrue();
});

it('does not count untouched default cost settings as done', function (): void {
    CostSetting::query()->create(['tenant_id' => $this->tenant->id]);

    $steps = collect($this->actingAs($this->user)->getJson('/api/onboarding')->json('data.steps'))->keyBy('key');

    expect($steps['costs']['complete'])->toBeFalse();

    CostSetting::query()->where('tenant_id', $this->tenant->id)->update(['packaging_cost' => Money::fromRupees(18)]);

    $steps = collect($this->actingAs($this->user)->getJson('/api/onboarding')->json('data.steps'))->keyBy('key');

    expect($steps['costs']['complete'])->toBeTrue()
        ->and($steps['costs']['evidence'])->toContain('₹18');
});

it('turns stated volume into a first revenue target', function (): void {
    $this->actingAs($this->user)->putJson('/api/onboarding/business', [
        'brand_name' => 'Kaira Living',
        'categories' => ['Ethnic wear', 'Accessories'],
        'monthly_orders' => 400,
        'average_order_value' => 2500,
        'gst_state' => 'Maharashtra',
    ])->assertOk();

    $tenant = $this->tenant->fresh();
    $benchmark = Benchmark::query()->where('tenant_id', $this->tenant->id)->first();

    expect($tenant->name)->toBe('Kaira Living')
        ->and($tenant->gst_state)->toBe('Maharashtra')
        ->and($tenant->onboarding_state['business']['categories'])->toBe(['Ethnic wear', 'Accessories'])
        // 400 orders × ₹2,500 = ₹10,00,000 a month.
        ->and($benchmark->monthly_revenue_target)->toBe(Money::fromRupees(1000000));
});

it('marks the business step complete once it is answered', function (): void {
    $this->actingAs($this->user)->putJson('/api/onboarding/business', ['brand_name' => 'Kaira Living']);

    $steps = collect($this->actingAs($this->user)->getJson('/api/onboarding')->json('data.steps'))->keyBy('key');

    expect($steps['business']['complete'])->toBeTrue();
});

it('lets a user dismiss the wizard without faking progress', function (): void {
    $this->actingAs($this->user)->postJson('/api/onboarding/dismiss')->assertOk();

    $response = $this->actingAs($this->user)->getJson('/api/onboarding');

    expect($response->json('data.dismissed'))->toBeTrue()
        // Dismissing is not completing.
        ->and($response->json('data.progress.done'))->toBe(0);
});
