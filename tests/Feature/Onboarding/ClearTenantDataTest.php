<?php

declare(strict_types=1);

use App\Console\Commands\ClearTenantData;
use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Enums\SyncStatus;
use App\Models\Connector;
use App\Models\CostSetting;
use App\Models\Sku;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->tenant = $this->tenant(['slug' => 'kaira-living', 'is_demo' => true]);

    User::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Owner',
        'email' => 'owner@example.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);

    CostSetting::query()->create(['tenant_id' => $this->tenant->id]);

    Connector::query()->create([
        'tenant_id' => $this->tenant->id,
        'connector_id' => 'shopify',
        'status' => ConnectorStatus::Connected,
        'auth_type' => AuthType::OAuth,
        'credentials' => ['shop_domain' => 'kaira.myshopify.com', 'access_token' => 't'],
        'sync_cursor' => ['orders' => '2026-08-01T00:00:00+05:30'],
        'last_synced_at' => now(),
    ]);

    Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => 'demo-v-1',
        'sku_code' => 'DEMO-1', 'name' => 'Demo SKU', 'selling_price' => 100000, 'cost_price' => 40000,
    ]);

    SyncRun::query()->create([
        'tenant_id' => $this->tenant->id, 'connector_id' => 'shopify', 'entity' => 'orders',
        'status' => SyncStatus::Success, 'trigger' => 'scheduled', 'started_at' => now(),
    ]);

    $this->otherTenant = $this->tenant();

    Sku::query()->create([
        'tenant_id' => $this->otherTenant->id, 'source' => 'shopify', 'external_id' => '222',
        'sku_code' => 'REAL-1', 'name' => 'Real SKU', 'selling_price' => 100000, 'cost_price' => 40000,
    ]);
});

it('clears a tenant of its data but keeps its users, connectors and settings', function (): void {
    $this->artisan('tenant:clear-data', ['tenant' => 'kaira-living', '--force' => true])->assertSuccessful();

    expect(DB::table('skus')->where('tenant_id', $this->tenant->id)->count())->toBe(0)
        ->and(DB::table('sync_runs')->where('tenant_id', $this->tenant->id)->count())->toBe(0)
        ->and(DB::table('users')->where('tenant_id', $this->tenant->id)->count())->toBe(1)
        ->and(DB::table('cost_settings')->where('tenant_id', $this->tenant->id)->count())->toBe(1)
        ->and($this->tenant->fresh()->is_demo)->toBeFalse();

    $connector = DB::table('connectors')->where('tenant_id', $this->tenant->id)->first();

    expect($connector)->not->toBeNull()
        ->and(json_decode((string) $connector->sync_cursor, true))->toBe([])
        ->and($connector->last_synced_at)->toBeNull();
});

it('never touches another tenant', function (): void {
    $this->artisan('tenant:clear-data', ['tenant' => $this->tenant->id, '--force' => true])->assertSuccessful();

    expect(DB::table('skus')->where('tenant_id', $this->otherTenant->id)->count())->toBe(1);
});

it('deletes nothing when the prompt is declined', function (): void {
    $this->artisan('tenant:clear-data', ['tenant' => 'kaira-living'])
        ->expectsConfirmation('Permanently delete 2 rows for Test Brand? Users, connectors and settings are kept.', 'no')
        ->assertFailed();

    expect(DB::table('skus')->where('tenant_id', $this->tenant->id)->count())->toBe(1)
        ->and($this->tenant->fresh()->is_demo)->toBeTrue();
});

it('fails on an unknown tenant', function (): void {
    $this->artisan('tenant:clear-data', ['tenant' => 'no-such-brand', '--force' => true])->assertFailed();
});

it('decides for every tenant-scoped table whether it is cleared or kept', function (): void {
    $tenantScoped = collect(Schema::getTables(DB::connection()->getDatabaseName()))
        ->pluck('name')
        ->filter(fn (string $table): bool => Schema::hasColumn($table, 'tenant_id'))
        ->values()
        ->all();

    // A new tenant table must be classified here, or demo rows would silently survive a clear.
    expect([...ClearTenantData::DATA_TABLES, ...ClearTenantData::KEPT_TABLES])->toEqualCanonicalizing($tenantScoped);
});
