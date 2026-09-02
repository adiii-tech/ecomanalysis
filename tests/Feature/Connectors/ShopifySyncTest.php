<?php

declare(strict_types=1);

use App\Domain\Connectors\Actions\ConnectConnector;
use App\Domain\Connectors\Actions\RunConnectorSync;
use App\Enums\ConnectorStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Connector;
use App\Models\CostSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Http;

/** @return array<string, mixed> */
function shopifyOrder(int $id, string $updatedAt = '2026-08-20T10:00:00+05:30'): array
{
    return [
        'id' => $id,
        'name' => '#'.$id,
        'created_at' => '2026-08-20T10:00:00+05:30',
        'updated_at' => $updatedAt,
        'processed_at' => '2026-08-20T10:05:00+05:30',
        'cancelled_at' => null,
        'financial_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'INR',
        'total_tax' => '45.00',
        'payment_gateway_names' => ['razorpay'],
        'total_shipping_price_set' => ['shop_money' => ['amount' => '0.00']],
        'discount_codes' => [['code' => 'WELCOME10']],
        'customer' => [
            'id' => 900 + $id,
            'email' => "buyer{$id}@example.com",
            'first_name' => 'Test',
            'last_name' => 'Buyer',
            'phone' => '9812345678',
            'default_address' => ['city' => 'Mumbai', 'province' => 'Maharashtra', 'zip' => '400001'],
        ],
        'shipping_address' => ['city' => 'Mumbai', 'province' => 'Maharashtra', 'zip' => '400001'],
        'line_items' => [[
            'id' => 5000 + $id,
            'variant_id' => 111,
            'sku' => 'SKU-1',
            'title' => 'Test SKU',
            'quantity' => 2,
            'price' => '1000.00',
            'discount_allocations' => [['amount' => '100.00']],
            'tax_lines' => [['price' => '45.00']],
        ]],
        'refunds' => [],
    ];
}

beforeEach(function (): void {
    $this->tenant = $this->tenant();

    CostSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'packaging_cost' => 2000,
        'default_shipping_cost' => 8000,
        'gateway_fee_pct' => 2.0,
    ]);

    Sku::query()->create([
        'tenant_id' => $this->tenant->id,
        'source' => 'shopify',
        'external_id' => '111',
        'sku_code' => 'SKU-1',
        'name' => 'Test SKU',
        'selling_price' => 100000,
        'cost_price' => 40000,
    ]);
});

it('verifies credentials against the shop endpoint before storing them', function (): void {
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living', 'currency' => 'INR']]),
    ]);

    $result = app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com',
        'access_token' => 'shpat_test',
    ]);

    expect($result->ok)->toBeTrue()
        ->and($result->accountLabel)->toBe('Kaira Living');

    $connector = Connector::query()->where('connector_id', 'shopify')->firstOrFail();
    expect($connector->status)->toBe(ConnectorStatus::Connected)
        // Credentials must be encrypted at rest, never stored in the clear.
        ->and($connector->getRawOriginal('credentials'))->not->toContain('shpat_test')
        ->and($connector->credentials['access_token'])->toBe('shpat_test');
});

it('refuses bad credentials instead of storing them', function (): void {
    Http::fake(['*/shop.json' => Http::response(['errors' => 'Unauthorized'], 401)]);

    $result = app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com',
        'access_token' => 'wrong',
    ]);

    expect($result->ok)->toBeFalse();
    expect(Connector::query()->where('connector_id', 'shopify')->first()->status)
        ->toBe(ConnectorStatus::Error);
});

it('pulls a Shopify order all the way through to a resolved contribution margin', function (): void {
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living', 'currency' => 'INR']]),
        '*/orders.json*' => Http::response(['orders' => [shopifyOrder(1001)]]),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com',
        'access_token' => 'shpat_test',
    ]);

    $report = app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'orders', 'manual');

    expect($report->ok())->toBeTrue()
        ->and($report->fetched)->toBe(1)
        ->and($report->upserted)->toBe(1);

    $order = Order::query()->where('external_id', '1001')->firstOrFail();

    // 2 x ₹1000 gross, ₹100 off, ₹800 COGS, ₹80 shipping, ₹20 packaging, 2% gateway.
    expect($order->order_number)->toBe('#1001')
        ->and($order->gross_amount)->toBe(200000)
        ->and($order->discount_amount)->toBe(10000)
        ->and($order->net_amount)->toBe(190000)
        ->and($order->cogs_amount)->toBe(80000)
        ->and($order->payment_mode)->toBe(PaymentMode::Prepaid)
        ->and($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->shipping_state)->toBe('Maharashtra')
        ->and($order->contribution_margin)->toBe(190000 - 80000 - 8000 - 2000 - 3800);

    expect(OrderItem::query()->where('order_id', $order->id)->count())->toBe(1);

    // Customer PII must be masked and hashed, never stored in the clear.
    $customer = Customer::query()->firstOrFail();
    expect($customer->masked_email)->toStartWith('bu*')
        ->and($customer->masked_email)->toEndWith('@example.com')
        ->and($customer->masked_email)->not->toContain('buyer1001')
        ->and($customer->email_hash)->not->toBeNull()
        ->and($customer->state)->toBe('Maharashtra');
});

it('is idempotent — replaying the same order does not duplicate anything', function (): void {
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/orders.json*' => Http::response(['orders' => [shopifyOrder(1001)]]),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com',
        'access_token' => 'shpat_test',
    ]);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'orders', 'manual');
    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'orders', 'manual');
    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'orders', 'manual');

    expect(Order::query()->count())->toBe(1)
        ->and(OrderItem::query()->count())->toBe(1)
        ->and(Customer::query()->count())->toBe(1);
});

it('advances the cursor so the next sync only asks for what changed', function (): void {
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/orders.json*' => Http::response(['orders' => [shopifyOrder(1001, '2026-08-25T09:00:00+05:30')]]),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com',
        'access_token' => 'shpat_test',
    ]);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'orders', 'manual');

    $connector = Connector::query()->where('connector_id', 'shopify')->firstOrFail();
    expect($connector->cursorFor('orders'))->toContain('2026-08-25');
});

it('records a failed sync run instead of failing silently', function (): void {
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/orders.json*' => Http::response(['errors' => 'boom'], 500),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com',
        'access_token' => 'shpat_test',
    ]);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'orders', 'manual');

    $run = SyncRun::query()->latest('id')->firstOrFail();
    expect($run->entity)->toBe('orders')
        ->and(Order::query()->count())->toBe(0);
});

it('will not sync a connector that is not connected', function (): void {
    $report = app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'orders', 'manual');

    expect($report->ok())->toBeFalse()
        ->and($report->error)->toContain('not connected');
});
