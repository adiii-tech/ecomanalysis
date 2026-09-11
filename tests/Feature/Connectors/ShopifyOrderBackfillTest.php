<?php

declare(strict_types=1);

use App\Domain\Connectors\Actions\RunConnectorSync;
use App\Domain\Connectors\Jobs\SyncConnectorEntity;
use App\Domain\Rollups\Jobs\RebuildRollups;
use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use App\Models\CostSetting;
use App\Models\Order;
use App\Models\Sku;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    CostSetting::query()->create(['tenant_id' => $this->tenant->id]);

    Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '111',
        'sku_code' => 'SKU-1', 'name' => 'Test SKU', 'selling_price' => 100000, 'cost_price' => 40000,
    ]);

    $this->connector = Connector::query()->create([
        'tenant_id' => $this->tenant->id,
        'connector_id' => 'shopify',
        'status' => ConnectorStatus::Connected,
        'auth_type' => AuthType::OAuth,
        'credentials' => ['shop_domain' => 'kaira.myshopify.com', 'access_token' => 't'],
        'sync_cursor' => ['orders' => '2026-09-01T00:00:00+05:30'],
    ]);
});

it('fetches a window by creation date and leaves the incremental cursor where it was', function (): void {
    Http::fake([
        '*/orders.json*' => Http::response(['orders' => [[
            'id' => 7001,
            'name' => '#7001',
            'created_at' => '2024-01-05T10:00:00+05:30',
            'updated_at' => '2024-01-05T10:00:00+05:30',
            'financial_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'INR',
            'total_tax' => '0.00',
            'payment_gateway_names' => ['razorpay'],
            'total_shipping_price_set' => ['shop_money' => ['amount' => '0.00']],
            'shipping_address' => ['city' => 'Delhi', 'province' => 'Delhi', 'zip' => '110001'],
            'line_items' => [[
                'id' => 8001, 'variant_id' => 111, 'sku' => 'SKU-1', 'title' => 'Test SKU',
                'quantity' => 1, 'price' => '1000.00', 'discount_allocations' => [], 'tax_lines' => [],
            ]],
            'refunds' => [],
        ]]]),
    ]);

    $report = app(RunConnectorSync::class)->handle(
        $this->tenant, 'shopify', 'orders', 'backfill', true,
        CarbonImmutable::parse('2024-01-01T00:00:00+05:30'),
        CarbonImmutable::parse('2024-01-15T00:00:00+05:30'),
    );

    expect($report->ok())->toBeTrue()
        ->and(Order::query()->where('external_id', '7001')->exists())->toBeTrue()
        ->and($this->connector->fresh()->cursorFor('orders'))->toBe('2026-09-01T00:00:00+05:30');

    Http::assertSent(function (Request $request): bool {
        $url = urldecode($request->url());

        return str_contains($url, 'orders.json')
            && str_contains($url, 'created_at_min=2024-01-01')
            && str_contains($url, 'created_at_max=2024-01-15')
            && ! str_contains($url, 'updated_at_min');
    });
});

it('queues chained windows up to where the first sync began, then a full rollup', function (): void {
    Bus::fake();
    $this->travelTo(CarbonImmutable::parse('2026-09-11 12:00:00', 'Asia/Kolkata'));

    $this->artisan('shopify:backfill-orders', ['tenant' => $this->tenant->id, '--since' => '2026-05-01', '--days' => 14])
        ->assertSuccessful();

    // 1 May → 13 June (90 days before today) in 14-day steps is four windows.
    Bus::assertChained([
        SyncConnectorEntity::class,
        SyncConnectorEntity::class,
        SyncConnectorEntity::class,
        SyncConnectorEntity::class,
        RebuildRollups::class,
    ]);

    $first = Bus::dispatched(SyncConnectorEntity::class)->first();
    $last = unserialize($first->chained[2]);

    expect($first->trigger)->toBe('backfill')
        ->and($first->rebuildRollups)->toBeFalse()
        ->and($first->since)->toStartWith('2026-05-01')
        ->and($first->until)->toStartWith('2026-05-15')
        ->and($last->until)->toStartWith('2026-06-13');
});

it('refuses to backfill a store that is not connected', function (): void {
    Bus::fake();
    $this->connector->forceFill(['status' => ConnectorStatus::Disconnected])->save();

    $this->artisan('shopify:backfill-orders', ['tenant' => $this->tenant->id, '--since' => '2025-01-01'])
        ->assertFailed();

    Bus::assertNothingDispatched();
});

it('keeps a history window from blocking the live sync of the same entity', function (): void {
    $live = (new SyncConnectorEntity($this->tenant->id, 'shopify', 'orders'))->middleware()[0];
    $window = (new SyncConnectorEntity($this->tenant->id, 'shopify', 'orders', until: '2024-01-15T00:00:00+05:30'))->middleware()[0];

    expect($window->key)->not->toBe($live->key);
});
