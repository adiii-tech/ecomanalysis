<?php

declare(strict_types=1);

use App\Domain\Connectors\Actions\RunConnectorSync;
use App\Domain\Connectors\Jobs\SyncConnectorEntity;
use App\Domain\Connectors\QueueNames;
use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use App\Models\CostSetting;
use App\Models\Order;
use App\Models\Sku;
use App\Models\SyncRun;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    CostSetting::query()->create(['tenant_id' => $this->tenant->id]);

    Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '111',
        'sku_code' => 'SKU-1', 'name' => 'Test SKU', 'selling_price' => 100000, 'cost_price' => 40000,
    ]);

    Connector::query()->create([
        'tenant_id' => $this->tenant->id,
        'connector_id' => 'shopify',
        'status' => ConnectorStatus::Connected,
        'auth_type' => AuthType::OAuth,
        'credentials' => ['shop_domain' => 'kaira.myshopify.com', 'access_token' => 't'],
    ]);
});

it('lists every queue a worker must listen on', function (): void {
    $names = QueueNames::all();

    expect($names)->toContain('sync-shopify', 'sync-meta', 'sync-ga4', 'rollups', 'webhooks', 'default')
        // Phase-2 stubs never dispatch, so they must not appear.
        ->and($names)->not->toContain('sync-shiprocket');
});

it('dispatches a sync onto that connector own queue', function (): void {
    Queue::fake();

    SyncConnectorEntity::dispatch($this->tenant->id, 'shopify', 'orders', 'manual');

    Queue::assertPushedOn('sync-shopify', SyncConnectorEntity::class);
});

it('runs the queued job for real and lands an order', function (): void {
    Http::fake([
        '*/orders.json*' => Http::response(['orders' => [[
            'id' => 3003,
            'name' => '#3003',
            'created_at' => '2026-08-22T10:00:00+05:30',
            'updated_at' => '2026-08-22T10:00:00+05:30',
            'financial_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'INR',
            'total_tax' => '0.00',
            'payment_gateway_names' => ['razorpay'],
            'total_shipping_price_set' => ['shop_money' => ['amount' => '0.00']],
            'shipping_address' => ['city' => 'Delhi', 'province' => 'Delhi', 'zip' => '110001'],
            'line_items' => [[
                'id' => 8001, 'variant_id' => 111, 'sku' => 'SKU-1', 'title' => 'Test SKU',
                'quantity' => 3, 'price' => '1000.00', 'discount_allocations' => [], 'tax_lines' => [],
            ]],
            'refunds' => [],
        ]]]),
    ]);

    // Execute the job exactly as the worker would.
    (new SyncConnectorEntity($this->tenant->id, 'shopify', 'orders', 'manual'))
        ->handle(app(RunConnectorSync::class), app(TenantContext::class));

    $order = Order::query()->withoutGlobalScopes()->where('external_id', '3003')->firstOrFail();

    expect($order->units_count)->toBe(3)
        ->and($order->cogs_amount)->toBe(120000)
        ->and($order->shipping_state)->toBe('Delhi');

    $run = SyncRun::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
    expect($run->status->value)->toBe('success')
        ->and($run->records_upserted)->toBe(1)
        ->and($run->trigger)->toBe('manual');
});

it('queues nothing for a connector that is not due yet', function (): void {
    Queue::fake();

    // A run just happened, so its cadence has not elapsed.
    SyncRun::query()->create([
        'tenant_id' => $this->tenant->id,
        'connector_id' => 'shopify',
        'entity' => 'orders',
        'status' => 'success',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $this->artisan('connectors:schedule')->assertSuccessful();

    Queue::assertNotPushed(SyncConnectorEntity::class, fn (SyncConnectorEntity $job): bool => $job->entity === 'orders');
});

it('queues a connector whose cadence has elapsed', function (): void {
    Queue::fake();

    SyncRun::query()->create([
        'tenant_id' => $this->tenant->id,
        'connector_id' => 'shopify',
        'entity' => 'orders',
        'status' => 'success',
        'started_at' => now()->subHours(2),
        'finished_at' => now()->subHours(2),
    ]);

    $this->artisan('connectors:schedule')->assertSuccessful();

    Queue::assertPushed(SyncConnectorEntity::class, fn (SyncConnectorEntity $job): bool => $job->entity === 'orders');
});
