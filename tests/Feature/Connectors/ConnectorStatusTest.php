<?php

declare(strict_types=1);

use App\Domain\Connectors\Jobs\SyncConnectorEntity;
use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->tenant = $this->tenant();

    $this->connector = Connector::query()->create([
        'tenant_id' => $this->tenant->id,
        'connector_id' => 'shopify',
        'status' => ConnectorStatus::Connected,
        'auth_type' => AuthType::OAuth,
        'credentials' => ['shop_domain' => 'kaira.myshopify.com', 'access_token' => 't'],
        'has_secret' => true,
    ]);
});

it('stays connected while a sync runs and after one fails', function (ConnectorStatus $status): void {
    $this->connector->forceFill(['status' => $status])->save();

    expect($this->connector->fresh()->isConnected())->toBeTrue()
        ->and(Connector::query()->connected()->count())->toBe(1);
})->with([
    'syncing' => ConnectorStatus::Syncing,
    'last sync failed' => ConnectorStatus::Error,
]);

it('is not connected once disconnected, before setup is finished, or when authorisation never stored credentials', function (ConnectorStatus $status, ?array $credentials): void {
    $this->connector->forceFill(['status' => $status, 'credentials' => $credentials])->save();

    expect($this->connector->fresh()->isConnected())->toBeFalse()
        ->and(Connector::query()->connected()->count())->toBe(0);
})->with([
    'disconnected' => [ConnectorStatus::Disconnected, null],
    'needs setup' => [ConnectorStatus::NeedsSetup, ['access_token' => 't']],
    'failed first authorisation' => [ConnectorStatus::Error, null],
]);

it('keeps scheduling a connector whose last sync was cut off mid-run', function (): void {
    // A worker stopped mid-sync leaves the status at syncing; that must not end the schedule.
    $this->connector->forceFill(['status' => ConnectorStatus::Syncing])->save();

    $this->artisan('connectors:schedule', ['--tenant' => $this->tenant->id, '--dry-run' => true])
        ->expectsOutputToContain('shopify · orders')
        ->assertSuccessful();
});

it('shows a syncing connector as connected on the connectors page', function (): void {
    $this->connector->forceFill(['status' => ConnectorStatus::Syncing])->save();

    $rows = $this->actingAs($this->userFor($this->tenant))
        ->getJson('/api/connectors')
        ->assertOk()
        ->json('data.live');

    $shopify = collect($rows)->firstWhere('id', 'shopify');

    expect($shopify['is_connected'])->toBeTrue()
        ->and($shopify['status'])->toBe('syncing');
});

it('accepts a manual sync while another sync is still running', function (): void {
    Queue::fake();
    $this->connector->forceFill(['status' => ConnectorStatus::Syncing])->save();

    $this->actingAs($this->userFor($this->tenant))
        ->postJson('/api/connectors/shopify/sync')
        ->assertOk();

    Queue::assertPushed(SyncConnectorEntity::class, 8);
});
