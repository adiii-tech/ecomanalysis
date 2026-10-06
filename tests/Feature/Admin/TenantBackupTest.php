<?php

declare(strict_types=1);

use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Models\AiSetting;
use App\Models\Connector;
use App\Models\NotificationSetting;
use App\Models\Sku;
use App\Support\Money;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->admin = $this->userFor($this->tenant);
});

it("downloads a SQL backup containing only this tenant's rows", function (): void {
    Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => 'v1',
        'sku_code' => 'MINE-1', 'name' => 'My SKU', 'cost_price' => Money::fromRupees(100), 'selling_price' => Money::fromRupees(200),
    ]);

    $other = $this->tenant(['name' => 'Rival Brand']);
    Sku::query()->create([
        'tenant_id' => $other->id, 'source' => 'shopify', 'external_id' => 'v2',
        'sku_code' => 'RIVAL-1', 'name' => 'Rival SKU', 'cost_price' => Money::fromRupees(100), 'selling_price' => Money::fromRupees(200),
    ]);

    $sql = $this->actingAs($this->admin)->get('/api/admin/backup')->assertOk()->streamedContent();

    expect($sql)->toContain('MINE-1')
        ->not->toContain('RIVAL-1')
        ->not->toContain('Rival Brand');
});

it('redacts connector credentials, WhatsApp tokens and AI keys from the dump', function (): void {
    Connector::query()->create([
        'tenant_id' => $this->tenant->id, 'connector_id' => 'shopify', 'status' => ConnectorStatus::Connected,
        'auth_type' => AuthType::OAuth, 'credentials' => ['access_token' => 'shpat_supersecret'],
        'sync_cursor' => [], 'last_synced_at' => now(),
    ]);
    NotificationSetting::query()->create(['tenant_id' => $this->tenant->id, 'whatsapp_token' => 'wa-supersecret']);
    AiSetting::query()->create(['tenant_id' => $this->tenant->id, 'api_key' => 'sk-ant-supersecret']);

    $sql = $this->actingAs($this->admin)->get('/api/admin/backup')->assertOk()->streamedContent();

    expect($sql)->not->toContain('shpat_supersecret')
        ->not->toContain('wa-supersecret')
        ->not->toContain('sk-ant-supersecret')
        ->toContain('`connectors`')
        ->toContain('`notification_settings`')
        ->toContain('`ai_settings`');
});

it('includes the tenant row itself so the dump can stand on its own', function (): void {
    $sql = $this->actingAs($this->admin)->get('/api/admin/backup')->assertOk()->streamedContent();

    expect($sql)->toContain('`tenants`')
        ->toContain((string) $this->tenant->id);
});

it('will not let a role without backup access download one', function (): void {
    $viewer = $this->userFor($this->tenant, ['dashboard.kpi_strip.view']);

    $this->actingAs($viewer)->get('/api/admin/backup')->assertForbidden();
});
