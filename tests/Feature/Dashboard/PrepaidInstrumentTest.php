<?php

declare(strict_types=1);

use App\Domain\Rollups\Actions\RebuildDailyMetrics;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Channel;
use App\Models\CostSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Support\Money;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->tenant = $this->tenant();

    CostSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'packaging_cost' => Money::fromRupees(20),
        'per_order_fixed_cost' => 0,
        'cod_charge' => Money::fromRupees(30),
        'default_shipping_cost' => Money::fromRupees(80),
        'gateway_fee_pct' => 2.0,
        'return_handling_cost' => Money::fromRupees(100),
        'rto_handling_cost' => Money::fromRupees(150),
    ]);

    $this->channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $this->sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'v1',
        'sku_code' => 'SKU-1', 'name' => 'Test SKU',
        'mrp' => Money::fromRupees(1500), 'selling_price' => Money::fromRupees(1000),
        'cost_price' => Money::fromRupees(400),
    ]);

    $this->placeOrder = function (PaymentMode $mode = PaymentMode::Prepaid): Order {
        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'source' => 'test',
            'external_id' => 'o-'.uniqid(),
            'order_number' => '#'.uniqid(),
            'placed_at' => CarbonImmutable::now()->subDays(2),
            'status' => OrderStatus::Delivered,
            'payment_mode' => $mode,
            'shipping_state' => 'Maharashtra',
        ]);

        OrderItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'sku_id' => $this->sku->id,
            'sku_code' => 'SKU-1',
            'qty' => 1,
            'unit_price' => Money::fromRupees(1000),
            'cogs_unit' => Money::fromRupees(400),
        ]);

        return $order;
    };

    $this->pay = function (Order $order, string $method, int $rupees = 1000, string $status = 'success', string $kind = 'sale'): Transaction {
        return Transaction::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'source' => 'test',
            'external_id' => 't-'.uniqid(),
            'gateway' => 'Razorpay',
            'method' => $method,
            'kind' => $kind,
            'status' => $status,
            'amount' => Money::fromRupees($rupees),
            'fee' => 0,
            'processed_at' => $order->placed_at,
        ]);
    };

    $this->settle = function (): void {
        Order::query()->get()->each(fn (Order $order) => app(ComputeOrderEconomics::class)->handle($order->fresh()));

        app(RebuildDailyMetrics::class)->handle(
            $this->tenant,
            CarbonImmutable::now()->subDays(60),
            CarbonImmutable::now(),
        );
    };

    $this->fetch = function (string $query = ''): array {
        $user = $this->userFor($this->tenant, ['dashboard.payment_mode_economics.view']);

        return $this->actingAs($user)
            ->getJson('/api/dashboard/payment-mode-economics?preset=last_30_days'.$query)
            ->assertOk()
            ->json('data');
    };
});

it('splits the prepaid half by the instrument that actually paid', function (): void {
    ($this->pay)(($this->placeOrder)(), 'UPI');
    ($this->pay)(($this->placeOrder)(), 'UPI');
    ($this->pay)(($this->placeOrder)(), 'Credit Card');
    ($this->pay)(($this->placeOrder)(), 'Net Banking');
    ($this->placeOrder)(PaymentMode::Cod);
    ($this->settle)();

    $breakdown = ($this->fetch)()['prepaid_breakdown'];

    expect($breakdown['orders'])->toBe(4)
        ->and((float) $breakdown['identified_pct'])->toBe(100.0)
        ->and($breakdown['caveat'])->toBeNull();

    expect(collect($breakdown['rows'])->pluck('orders', 'instrument')->all())
        ->toBe(['upi' => 2, 'card' => 1, 'netbanking' => 1]);

    $upi = collect($breakdown['rows'])->firstWhere('instrument', 'upi');
    expect($upi['label'])->toBe('UPI')
        ->and((float) $upi['share_of_orders'])->toBe(50.0)
        ->and($upi['net_sales'])->toBe(Money::fromRupees(2000))
        ->and($upi['aov'])->toBe(Money::fromRupees(1000));
});

it('reconciles exactly with the prepaid card above it', function (): void {
    ($this->pay)(($this->placeOrder)(), 'UPI');
    ($this->pay)(($this->placeOrder)(), 'Visa');
    ($this->placeOrder)(PaymentMode::Cod);
    ($this->settle)();

    $data = ($this->fetch)();
    $prepaid = collect($data['rows'])->firstWhere('mode', 'prepaid');

    expect($data['prepaid_breakdown']['orders'])->toBe($prepaid['orders'])
        ->and($data['prepaid_breakdown']['net_sales'])->toBe($prepaid['net_sales'])
        ->and(collect($data['prepaid_breakdown']['rows'])->sum('orders'))->toBe($prepaid['orders']);
});

it('counts a split payment once, under its largest leg', function (): void {
    $order = ($this->placeOrder)();
    ($this->pay)($order, 'Net Banking', rupees: 200);
    ($this->pay)($order, 'UPI', rupees: 800);
    ($this->settle)();

    $breakdown = ($this->fetch)()['prepaid_breakdown'];

    expect($breakdown['orders'])->toBe(1)
        ->and(collect($breakdown['rows'])->pluck('orders', 'instrument')->all())->toBe(['upi' => 1]);
});

it('ignores refunds and failed attempts when reading the instrument', function (): void {
    $order = ($this->placeOrder)();
    ($this->pay)($order, 'Credit Card', status: 'failed');
    ($this->pay)($order, 'UPI');
    ($this->pay)($order, 'UPI', rupees: -1000, kind: 'refund');
    ($this->settle)();

    $breakdown = ($this->fetch)()['prepaid_breakdown'];

    expect(collect($breakdown['rows'])->pluck('orders', 'instrument')->all())->toBe(['upi' => 1]);
});

it('shows an order with no transaction as unattributed rather than dropping it', function (): void {
    ($this->pay)(($this->placeOrder)(), 'UPI');
    ($this->placeOrder)();
    ($this->settle)();

    $breakdown = ($this->fetch)()['prepaid_breakdown'];

    expect($breakdown['orders'])->toBe(2)
        ->and((float) $breakdown['identified_pct'])->toBe(50.0)
        ->and($breakdown['caveat'])->toContain('50%');

    $rows = collect($breakdown['rows']);
    expect($rows->last()['instrument'])->toBe('unattributed')
        ->and($rows->last()['identified'])->toBeFalse()
        ->and($rows->last()['orders'])->toBe(1);
});

it('leaves the breakdown empty when the dashboard is filtered to COD', function (): void {
    ($this->pay)(($this->placeOrder)(), 'UPI');
    ($this->placeOrder)(PaymentMode::Cod);
    ($this->settle)();

    expect(($this->fetch)('&payment_mode=cod')['prepaid_breakdown']['rows'])->toBe([]);
});

it('never reads another tenant transactions', function (): void {
    ($this->pay)(($this->placeOrder)(), 'UPI');

    $other = Tenant::query()->create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'timezone' => 'Asia/Kolkata']);

    app(TenantContext::class)->runAs($other, function () use ($other): void {
        $order = Order::query()->create([
            'tenant_id' => $other->id,
            'source' => 'test',
            'external_id' => 'o-'.uniqid(),
            'placed_at' => CarbonImmutable::now()->subDays(2),
            'status' => OrderStatus::Delivered,
            'payment_mode' => PaymentMode::Prepaid,
        ]);

        Transaction::query()->create([
            'tenant_id' => $other->id,
            'order_id' => $order->id,
            'source' => 'test',
            'external_id' => 't-'.uniqid(),
            'gateway' => 'Razorpay',
            'method' => 'Credit Card',
            'kind' => 'sale',
            'status' => 'success',
            'amount' => Money::fromRupees(1000),
            'fee' => 0,
            'processed_at' => $order->placed_at,
        ]);
    });

    ($this->settle)();

    $mine = ($this->fetch)()['prepaid_breakdown'];

    expect($mine['orders'])->toBe(1)
        ->and(collect($mine['rows'])->pluck('orders', 'instrument')->all())->toBe(['upi' => 1]);
});
