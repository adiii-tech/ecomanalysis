<?php

declare(strict_types=1);

use App\Domain\Customers\Segments\SegmentBuilder;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Support\Money;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);

    $this->vip = Customer::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'c-vip',
        'name' => 'Asha Rao', 'city' => 'Mumbai', 'state' => 'Maharashtra',
        'email_encrypted' => 'asha@example.test', 'masked_email' => 'a***@example.test',
        'phone_encrypted' => '9812345678', 'masked_phone' => '98***5678',
        'orders_count' => 9, 'total_spent' => Money::fromRupees(84000), 'aov' => Money::fromRupees(9333),
        'rfm_segment' => 'champions', 'accepts_marketing' => true, 'days_since_last_order' => 12,
        'last_order_at' => CarbonImmutable::now()->subDays(12),
    ]);

    $this->quiet = Customer::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'c-quiet',
        'name' => 'Rahul Mehta', 'city' => 'Pune', 'state' => 'Maharashtra',
        'email_encrypted' => 'rahul@example.test', 'masked_email' => 'r***@example.test',
        'orders_count' => 3, 'total_spent' => Money::fromRupees(21000),
        'rfm_segment' => 'at_risk', 'accepts_marketing' => false, 'days_since_last_order' => 190,
        'last_order_at' => CarbonImmutable::now()->subDays(190),
    ]);

    $this->small = Customer::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'c-small',
        'name' => 'Neha Singh', 'city' => 'Delhi', 'state' => 'Delhi',
        'orders_count' => 1, 'total_spent' => Money::fromRupees(1800),
        'rfm_segment' => 'new', 'accepts_marketing' => true, 'days_since_last_order' => 5,
    ]);
});

it('filters on money fields in rupees while matching paise in the database', function (): void {
    $matches = app(SegmentBuilder::class)->query([
        'match' => 'all',
        'conditions' => [['field' => 'total_spent', 'operator' => 'gt', 'value' => 20000]],
    ])->pluck('external_id');

    expect($matches)->toContain('c-vip', 'c-quiet')
        ->and($matches)->not->toContain('c-small');
});

it('combines rules with all and any', function (): void {
    $builder = app(SegmentBuilder::class);

    $all = $builder->query(['match' => 'all', 'conditions' => [
        ['field' => 'state', 'operator' => 'eq', 'value' => 'Maharashtra'],
        ['field' => 'orders_count', 'operator' => 'gte', 'value' => 5],
    ]])->pluck('external_id');

    $any = $builder->query(['match' => 'any', 'conditions' => [
        ['field' => 'state', 'operator' => 'eq', 'value' => 'Delhi'],
        ['field' => 'orders_count', 'operator' => 'gte', 'value' => 5],
    ]])->pluck('external_id');

    expect($all->all())->toBe(['c-vip'])
        ->and($any)->toContain('c-vip', 'c-small')
        ->and($any)->not->toContain('c-quiet');
});

it('refuses a field or operator that is not on the whitelist', function (): void {
    $builder = app(SegmentBuilder::class);

    expect($builder->problems(['conditions' => [['field' => 'password', 'operator' => 'eq', 'value' => 'x']]]))
        ->toHaveCount(1);

    expect($builder->problems(['conditions' => [['field' => 'state', 'operator' => 'between', 'value' => [1, 2]]]]))
        ->toHaveCount(1);

    expect($builder->problems(['conditions' => [['field' => 'rfm_segment', 'operator' => 'eq', 'value' => 'made_up']]]))
        ->toHaveCount(1);
});

it('treats a like value as text, not as a wildcard', function (): void {
    $matches = app(SegmentBuilder::class)->query([
        'conditions' => [['field' => 'city', 'operator' => 'contains', 'value' => '%']],
    ])->count();

    // A bare % must match nothing rather than everything.
    expect($matches)->toBe(0);
});

it('previews a segment with its contactable count and a caveat', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/segments/preview', [
        'rules' => ['match' => 'all', 'conditions' => [['field' => 'total_spent', 'operator' => 'gt', 'value' => 1000]]],
    ]);

    $response->assertOk();

    expect($response->json('data.member_count'))->toBe(3)
        ->and($response->json('data.contactable'))->toBe(2)
        ->and($response->json('data.caveat'))->toContain('never opted into marketing');
});

it('rejects a bad rule set before it is saved', function (): void {
    $this->actingAs($this->user)->postJson('/api/segments', [
        'name' => 'Broken',
        'rules' => ['conditions' => [['field' => 'not_a_field', 'operator' => 'eq', 'value' => 1]]],
    ])->assertStatus(422);

    expect(CustomerSegment::query()->count())->toBe(0);
});

it('saves a segment with its current size', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/segments', [
        'name' => 'High value',
        'rules' => ['conditions' => [['field' => 'total_spent', 'operator' => 'gt', 'value' => 20000]]],
    ]);

    $response->assertOk()->assertJsonPath('data.member_count', 2);

    $segment = CustomerSegment::query()->first();

    expect($segment->member_value)->toBe(Money::fromRupees(105000))
        ->and($segment->computed_at)->not->toBeNull();
});

it('hashes contact details for a Meta export and excludes anyone who did not opt in', function (): void {
    $segment = CustomerSegment::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'name' => 'Everyone',
        'rules' => ['conditions' => []],
    ]);

    $csv = $this->actingAs($this->user)->get("/api/segments/{$segment->id}/export/meta")->streamedContent();

    expect($csv)->toContain(hash('sha256', 'asha@example.test'))
        // Raw addresses must never reach Meta.
        ->and($csv)->not->toContain('asha@example.test')
        // Rahul never opted into marketing.
        ->and($csv)->not->toContain(hash('sha256', 'rahul@example.test'));
});

it('normalises Indian phone numbers for a WhatsApp export', function (): void {
    $segment = CustomerSegment::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'name' => 'Everyone',
        'rules' => ['conditions' => []],
    ]);

    $csv = $this->actingAs($this->user)->get("/api/segments/{$segment->id}/export/whatsapp")->streamedContent();

    expect($csv)->toContain('919812345678');
});

it('refuses a plaintext export without the unmasked-PII permission', function (): void {
    $segment = CustomerSegment::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'name' => 'Everyone',
        'rules' => ['conditions' => []],
    ]);

    $limited = $this->userFor($this->tenant, [
        'customer_intelligence.segments.export',
        'customer_intelligence.segments.view',
    ], 'ANALYST');

    $this->actingAs($limited)->getJson("/api/segments/{$segment->id}/export/klaviyo")
        ->assertForbidden()
        ->assertJsonPath('meta.required_permission', ['pii.unmask.view']);

    // The hashed export is still allowed, because nothing readable leaves.
    $this->actingAs($limited)->get("/api/segments/{$segment->id}/export/meta")->assertOk();
});

it('never counts another tenant customers', function (): void {
    $other = $this->tenant(['name' => 'Rival Brand']);
    Customer::query()->create([
        'tenant_id' => $other->id, 'source' => 'test', 'external_id' => 'c-rival',
        'name' => 'Rival Buyer', 'orders_count' => 50, 'total_spent' => Money::fromRupees(500000),
        'accepts_marketing' => true,
    ]);

    app(TenantContext::class)->set($this->tenant);

    $response = $this->actingAs($this->user)->postJson('/api/segments/preview', [
        'rules' => ['conditions' => [['field' => 'total_spent', 'operator' => 'gt', 'value' => 0]]],
    ]);

    expect($response->json('data.member_count'))->toBe(3);
});
