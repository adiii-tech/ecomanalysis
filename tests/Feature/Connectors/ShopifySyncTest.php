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
use App\Models\Transaction;
use Carbon\CarbonImmutable;
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

it('reads a synced prepaid order\'s transactions and books them', function (): void {
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/orders/2001/transactions.json' => Http::response(['transactions' => [
            ['id' => 9001, 'gateway' => 'razorpay', 'kind' => 'sale', 'status' => 'success',
                'amount' => '1000.00', 'processed_at' => now()->toIso8601String(),
                'payment_details' => ['credit_card_company' => null]],
        ]]),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com', 'access_token' => 'shpat_test',
    ]);

    $order = Order::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '2001',
        'order_number' => '#2001', 'placed_at' => now()->subDays(2), 'status' => OrderStatus::Delivered,
        'payment_mode' => PaymentMode::Prepaid, 'updated_at' => now()->subDays(2),
    ]);

    $report = app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'transactions', 'manual');

    expect($report->ok())->toBeTrue()
        ->and($report->upserted)->toBe(1);

    expect(Transaction::query()->where('order_id', $order->id)->first())
        ->gateway->toBe('razorpay')
        ->status->toBe('success');
});

it('never asks a COD order for its transactions', function (): void {
    Http::fake(['*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']])]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com', 'access_token' => 'shpat_test',
    ]);

    Order::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '2002',
        'order_number' => '#2002', 'placed_at' => now()->subDays(2), 'status' => OrderStatus::Delivered,
        'payment_mode' => PaymentMode::Cod, 'updated_at' => now()->subDays(2),
    ]);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'transactions', 'manual');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'orders/2002/transactions.json'));
});

it('does not let one order\'s failed transactions call push the cursor past it', function (): void {
    // #3001 is the older order and its own call fails; #3002 is newer.
    // Fixing this is the point of the test: the cursor must not leap past #3001.
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/orders/3001/transactions.json' => Http::response(['errors' => 'rate limited'], 429),
        '*/orders/3002/transactions.json' => Http::response(['transactions' => []]),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com', 'access_token' => 'shpat_test',
    ]);

    Order::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '3001',
        'order_number' => '#3001', 'placed_at' => now()->subDays(3), 'status' => OrderStatus::Delivered,
        'payment_mode' => PaymentMode::Prepaid, 'updated_at' => now()->subDays(3),
    ]);
    Order::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '3002',
        'order_number' => '#3002', 'placed_at' => now()->subDays(1), 'status' => OrderStatus::Delivered,
        'payment_mode' => PaymentMode::Prepaid, 'updated_at' => now()->subDays(1),
    ]);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'transactions', 'manual');

    $connector = Connector::query()->where('connector_id', 'shopify')->firstOrFail();
    $cursorAfter = CarbonImmutable::parse((string) $connector->cursorFor('transactions'));
    // The cursor sits at (or before) #3001's own updated_at, so the very next
    // run asks for #3001 again instead of treating it as already handled.
    expect($cursorAfter->lessThanOrEqualTo(now()->subDays(3)))->toBeTrue();
});

it('retries the order a failed call skipped, on the next run', function (): void {
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/orders/4001/transactions.json' => Http::sequence()
            ->push(['errors' => 'rate limited'], 429)
            ->push(['transactions' => [
                ['id' => 9002, 'gateway' => 'razorpay', 'kind' => 'sale', 'status' => 'success',
                    'amount' => '500.00', 'processed_at' => now()->toIso8601String()],
            ]]),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com', 'access_token' => 'shpat_test',
    ]);

    $order = Order::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '4001',
        'order_number' => '#4001', 'placed_at' => now()->subDays(2), 'status' => OrderStatus::Delivered,
        'payment_mode' => PaymentMode::Prepaid, 'updated_at' => now()->subDays(2),
    ]);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'transactions', 'manual');
    expect(Transaction::query()->where('order_id', $order->id)->count())->toBe(0);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'transactions', 'manual');
    expect(Transaction::query()->where('order_id', $order->id)->count())->toBe(1);
});

it('jumps the cursor to now once every due order is read cleanly', function (): void {
    Http::fake([
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/orders/5001/transactions.json' => Http::response(['transactions' => []]),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'shopify', [
        'shop_domain' => 'kaira.myshopify.com', 'access_token' => 'shpat_test',
    ]);

    Order::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'shopify', 'external_id' => '5001',
        'order_number' => '#5001', 'placed_at' => now()->subDays(2), 'status' => OrderStatus::Delivered,
        'payment_mode' => PaymentMode::Prepaid, 'updated_at' => now()->subDays(2),
    ]);

    app(RunConnectorSync::class)->handle($this->tenant, 'shopify', 'transactions', 'manual');

    $connector = Connector::query()->where('connector_id', 'shopify')->firstOrFail();
    $cursorAfter = CarbonImmutable::parse((string) $connector->cursorFor('transactions'));
    // Not left sitting two days back — a clean run with room to spare in the
    // page can safely fast-forward past today's new arrivals too.
    expect($cursorAfter->greaterThan(now()->subHour()))->toBeTrue();
});
