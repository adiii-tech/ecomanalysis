<?php

declare(strict_types=1);

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\ActivityLog as Activity;
use App\Models\Channel;
use App\Models\CostSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Support\Period;
use App\Support\WidgetFilters;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->tenant = $this->tenant();

    CostSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'packaging_cost' => 2000,
        'default_shipping_cost' => 8000,
        'gateway_fee_pct' => 2.0,
    ]);

    $channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'v1',
        'sku_code' => 'SKU-1', 'name' => 'Indigo Kurta', 'category' => 'Ethnic Wear',
        'mrp' => 150000, 'selling_price' => 100000, 'cost_price' => 40000, 'hsn' => '6204', 'gst_rate' => 5,
    ]);

    $order = Order::query()->create([
        'tenant_id' => $this->tenant->id,
        'channel_id' => $channel->id,
        'source' => 'test',
        'external_id' => 'o-1',
        'order_number' => '#4001',
        'placed_at' => CarbonImmutable::now()->subDays(2),
        'status' => OrderStatus::Delivered,
        'payment_mode' => PaymentMode::Prepaid,
        'shipping_state' => 'Maharashtra',
        'shipping_city' => 'Mumbai',
    ]);

    OrderItem::query()->create([
        'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $sku->id,
        'sku_code' => 'SKU-1', 'qty' => 2, 'unit_price' => 100000, 'discount' => 10000,
        'tax' => 4500, 'cogs_unit' => 40000,
    ]);

    app(ComputeOrderEconomics::class)->handle($order->fresh());

    $this->user = $this->userFor($this->tenant);
});

it('lists only the datasets the role may export', function (): void {
    $limited = $this->userFor($this->tenant, ['dashboard.recent_orders.export'], 'ANALYST');

    $keys = collect($this->actingAs($limited)->getJson('/api/export')->json('data.datasets'))->pluck('key');

    expect($keys)->toContain('orders')->and($keys)->not->toContain('customers');
});

it('refuses a dataset the role cannot export', function (): void {
    $limited = $this->userFor($this->tenant, ['dashboard.recent_orders.export'], 'ANALYST');

    $this->actingAs($limited)->getJson('/api/export/customers/csv')
        ->assertForbidden()
        ->assertJsonPath('meta.required_permission', ['customer_intelligence.top_customers.export']);
});

it('rejects an unknown dataset or format', function (): void {
    $this->actingAs($this->user)->getJson('/api/export/not-a-thing/csv')->assertNotFound();
    $this->actingAs($this->user)->getJson('/api/export/orders/docx')->assertStatus(422);
});

it('writes a CSV with the real numbers in rupees, not paise', function (): void {
    $response = $this->actingAs($this->user)->get('/api/export/orders/csv?preset=last_30_days');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('orders-');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Orders')
        ->and($csv)->toContain('Returns basis')
        ->and($csv)->toContain('#4001')
        ->and($csv)->toContain('Maharashtra')
        ->and($csv)->toContain('1900')
        ->and($csv)->not->toContain('190000');
});

it('produces a real PDF', function (): void {
    $response = $this->actingAs($this->user)->get('/api/export/orders/pdf?preset=last_30_days');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $body = $response->getContent();
    expect(substr($body, 0, 4))->toBe('%PDF')->and(strlen($body))->toBeGreaterThan(1000);
});

it('produces a real xlsx', function (): void {
    $response = $this->actingAs($this->user)->get('/api/export/orders/xlsx?preset=last_30_days');

    $response->assertOk();
    expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
});

it('records every export in the audit log', function (): void {
    $this->actingAs($this->user)->get('/api/export/orders/csv?preset=last_30_days')->assertOk();

    $activity = Activity::query()->where('log_name', 'export')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('export.downloaded')
        ->and($activity->properties['dataset'])->toBe('orders')
        ->and($activity->properties['format'])->toBe('csv');
});

it('masks customer PII unless the exporter holds pii.unmask.view', function (): void {
    Customer::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'c1',
        'name' => 'Aditi Sharma', 'masked_email' => 'ad****@example.com',
        'email_encrypted' => 'aditi@example.com', 'orders_count' => 3, 'total_spent' => 500000,
    ]);

    $masked = $this->userFor($this->tenant, ['customer_intelligence.top_customers.export'], 'ANALYST');
    $csv = $this->actingAs($masked)->get('/api/export/customers/csv')->streamedContent();

    expect($csv)->toContain('ad****@example.com')
        ->and($csv)->not->toContain('aditi@example.com')
        ->and($csv)->toContain('masked');
});

it('builds every catalogued dataset without blowing up', function (): void {
    $registry = app(DatasetRegistry::class);
    $filters = new WidgetFilters(Period::fromPreset('last_30_days'));

    foreach (array_keys(DatasetRegistry::catalogue()) as $key) {
        $dataset = $registry->build($key, $filters);

        expect($dataset->columns)->not->toBeEmpty("Dataset [{$key}] has no columns")
            ->and($dataset->headers())->not->toBeEmpty()
            ->and($dataset->body())->toBeArray();
    }
});
