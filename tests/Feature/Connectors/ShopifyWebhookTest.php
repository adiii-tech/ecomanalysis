<?php

declare(strict_types=1);

use App\Domain\Connectors\Jobs\ProcessShopifyWebhook;
use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use App\Models\CostSetting;
use App\Models\Order;
use App\Models\Sku;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;

function signed(array $payload, string $secret): string
{
    return base64_encode(hash_hmac('sha256', json_encode($payload), $secret, true));
}

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
        'credentials' => ['shop_domain' => 'kaira.myshopify.com', 'access_token' => 't', 'webhook_secret' => 'hush'],
    ]);

    $this->payload = [
        'id' => 2002,
        'name' => '#2002',
        'created_at' => '2026-08-21T10:00:00+05:30',
        'updated_at' => '2026-08-21T10:00:00+05:30',
        'financial_status' => 'paid',
        'fulfillment_status' => null,
        'currency' => 'INR',
        'total_tax' => '0.00',
        'payment_gateway_names' => ['cash on delivery (cod)'],
        'total_shipping_price_set' => ['shop_money' => ['amount' => '0.00']],
        'shipping_address' => ['city' => 'Pune', 'province' => 'Maharashtra', 'zip' => '411001'],
        'line_items' => [[
            'id' => 7001, 'variant_id' => 111, 'sku' => 'SKU-1', 'title' => 'Test SKU',
            'quantity' => 1, 'price' => '1000.00', 'discount_allocations' => [], 'tax_lines' => [],
        ]],
        'refunds' => [],
    ];
});

it('rejects a webhook whose signature does not match', function (): void {
    $this->withHeaders([
        'X-Shopify-Topic' => 'orders/create',
        'X-Shopify-Shop-Domain' => 'kaira.myshopify.com',
        'X-Shopify-Hmac-Sha256' => 'not-the-right-signature',
    ])->postJson('/webhooks/shopify', $this->payload)->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('rejects a webhook from a shop we do not know', function (): void {
    $this->withHeaders([
        'X-Shopify-Topic' => 'orders/create',
        'X-Shopify-Shop-Domain' => 'someone-else.myshopify.com',
        'X-Shopify-Hmac-Sha256' => signed($this->payload, 'hush'),
    ])->postJson('/webhooks/shopify', $this->payload)->assertNotFound();
});

it('accepts a signed webhook and queues it rather than parsing in the request', function (): void {
    Queue::fake();

    $this->withHeaders([
        'X-Shopify-Topic' => 'orders/create',
        'X-Shopify-Shop-Domain' => 'kaira.myshopify.com',
        'X-Shopify-Hmac-Sha256' => signed($this->payload, 'hush'),
    ])->postJson('/webhooks/shopify', $this->payload)
        ->assertOk()
        ->assertJson(['received' => true]);

    $event = WebhookEvent::query()->withoutGlobalScopes()->firstOrFail();
    expect($event->topic)->toBe('orders/create')->and($event->status)->toBe('pending');

    Queue::assertPushed(ProcessShopifyWebhook::class);
});

it('turns a queued webhook into a resolved order', function (): void {
    $this->withHeaders([
        'X-Shopify-Topic' => 'orders/create',
        'X-Shopify-Shop-Domain' => 'kaira.myshopify.com',
        'X-Shopify-Hmac-Sha256' => signed($this->payload, 'hush'),
    ])->postJson('/webhooks/shopify', $this->payload)->assertOk();

    $order = Order::query()->where('external_id', '2002')->firstOrFail();

    expect($order->order_number)->toBe('#2002')
        ->and($order->payment_mode->value)->toBe('cod')
        ->and($order->shipping_state)->toBe('Maharashtra')
        ->and($order->cogs_amount)->toBe(40000);

    expect(WebhookEvent::query()->withoutGlobalScopes()->first()->status)->toBe('processed');
});
