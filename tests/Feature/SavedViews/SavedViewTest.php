<?php

declare(strict_types=1);

use App\Models\SavedView;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);
    $this->colleague = $this->userFor($this->tenant, [], 'ANALYST');
});

it('saves a named filter set for a surface', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/saved-views', [
        'surface' => '/operations',
        'name' => 'Last quarter, marketplace only',
        'state' => ['preset' => 'last_quarter', 'channel' => 'marketplace', 'returns_basis' => 'return_date'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Last quarter, marketplace only')
        ->assertJsonPath('data.state.channel', 'marketplace')
        ->assertJsonPath('data.is_mine', true);
});

it('lists only this surface and only views the user may see', function (): void {
    $this->actingAs($this->user)->postJson('/api/saved-views', [
        'surface' => '/operations', 'name' => 'Mine', 'state' => ['channel' => 'all'],
    ]);
    $this->actingAs($this->user)->postJson('/api/saved-views', [
        'surface' => '/finance', 'name' => 'Other surface', 'state' => ['channel' => 'all'],
    ]);
    $this->actingAs($this->colleague)->postJson('/api/saved-views', [
        'surface' => '/operations', 'name' => 'Private to them', 'state' => ['channel' => 'all'],
    ]);
    $this->actingAs($this->colleague)->postJson('/api/saved-views', [
        'surface' => '/operations', 'name' => 'Shared with all', 'state' => ['channel' => 'd2c'], 'is_shared' => true,
    ]);

    $names = collect($this->actingAs($this->user)->getJson('/api/saved-views?surface=/operations')->json('data.rows'))
        ->pluck('name');

    expect($names)->toContain('Mine', 'Shared with all')
        ->and($names)->not->toContain('Private to them', 'Other surface');
});

it('overwrites a view of the same name rather than duplicating it', function (): void {
    $payload = ['surface' => '/operations', 'name' => 'Weekly', 'state' => ['channel' => 'all']];

    $this->actingAs($this->user)->postJson('/api/saved-views', $payload);
    $this->actingAs($this->user)->postJson('/api/saved-views', [...$payload, 'state' => ['channel' => 'd2c']]);

    $rows = $this->actingAs($this->user)->getJson('/api/saved-views?surface=/operations')->json('data.rows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['state']['channel'])->toBe('d2c');
});

it('lets only the owner edit or delete a shared view', function (): void {
    $view = SavedView::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->colleague->id,
        'surface' => '/operations',
        'name' => 'Theirs',
        'state' => ['channel' => 'all'],
        'is_shared' => true,
    ]);

    $this->actingAs($this->user)->putJson("/api/saved-views/{$view->id}", ['name' => 'Hijacked'])->assertForbidden();
    $this->actingAs($this->user)->deleteJson("/api/saved-views/{$view->id}")->assertForbidden();

    $this->actingAs($this->colleague)->putJson("/api/saved-views/{$view->id}", ['name' => 'Renamed'])->assertOk();

    expect($view->fresh()->name)->toBe('Renamed');
});

it('never leaks a view across tenants', function (): void {
    SavedView::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
        'surface' => '/operations', 'name' => 'Ours', 'state' => [], 'is_shared' => true,
    ]);

    $other = $this->tenant(['name' => 'Rival Brand']);
    $rival = $this->userFor($other);

    $rows = $this->actingAs($rival)->getJson('/api/saved-views?surface=/operations')->json('data.rows');

    expect($rows)->toBeEmpty();
});
