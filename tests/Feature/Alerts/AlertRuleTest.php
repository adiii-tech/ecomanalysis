<?php

declare(strict_types=1);

use App\Domain\Alerts\Services\MetricResolver;
use App\Domain\Alerts\Services\RuleEvaluator;
use App\Domain\Rollups\Actions\RebuildDailyMetrics;
use App\Domain\Rollups\Actions\RebuildStateRollup;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\Channel;
use App\Models\CostSetting;
use App\Models\NotificationSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use Carbon\CarbonImmutable;

function makeOrders(object $ctx, string $state, int $total, int $rto): void
{
    for ($i = 0; $i < $total; $i++) {
        $isRto = $i < $rto;

        $order = Order::query()->create([
            'tenant_id' => $ctx->tenant->id,
            'channel_id' => $ctx->channel->id,
            'source' => 'test',
            'external_id' => "o-{$state}-{$i}",
            'order_number' => "#{$state}{$i}",
            'placed_at' => CarbonImmutable::now()->subDays(3),
            'status' => $isRto ? OrderStatus::Rto : OrderStatus::Delivered,
            'is_rto' => $isRto,
            'payment_mode' => PaymentMode::Cod,
            'shipping_state' => $state,
        ]);

        OrderItem::query()->create([
            'tenant_id' => $ctx->tenant->id, 'order_id' => $order->id, 'sku_id' => $ctx->sku->id,
            'sku_code' => 'SKU-1', 'qty' => 1, 'unit_price' => 100000, 'cogs_unit' => 40000,
        ]);

        app(ComputeOrderEconomics::class)->handle($order->fresh());
    }
}

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    CostSetting::query()->create(['tenant_id' => $this->tenant->id]);

    $this->channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $this->sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'v1',
        'sku_code' => 'SKU-1', 'name' => 'Kurta', 'selling_price' => 100000, 'cost_price' => 40000,
    ]);

    // Bihar RTOs badly; Karnataka does not.
    makeOrders($this, 'Bihar', 20, 8);
    makeOrders($this, 'Karnataka', 20, 1);

    $from = CarbonImmutable::now()->subDays(30);
    $to = CarbonImmutable::now();
    app(RebuildDailyMetrics::class)->handle($this->tenant, $from, $to);
    app(RebuildStateRollup::class)->handle($this->tenant, $from, $to);

    $this->user = $this->userFor($this->tenant);
});

it('fires on the worst offender, not on every dimension', function (): void {
    $rule = AlertRule::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'RTO spike',
        'metric' => 'rto_rate_by_state',
        'operator' => 'gt',
        'threshold' => 25,
        'window_days' => 7,
    ]);

    $events = app(RuleEvaluator::class)->evaluate($rule);

    // Bihar is 40%, Karnataka 5% — only one event, naming Bihar.
    expect($events)->toHaveCount(1)
        ->and($events->first()->dimension_value)->toBe('Bihar')
        ->and($events->first()->observed_value)->toBe(40.0)
        ->and($events->first()->title)->toContain('Bihar')
        ->and($events->first()->severity)->toBe('critical');
});

it('stays quiet when nothing breaches', function (): void {
    $rule = AlertRule::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'RTO spike',
        'metric' => 'rto_rate_by_state',
        'operator' => 'gt',
        'threshold' => 60,
        'window_days' => 7,
    ]);

    expect(app(RuleEvaluator::class)->evaluate($rule))->toBeEmpty();
    expect($rule->fresh()->last_evaluated_at)->not->toBeNull();
});

it('does not re-fire while the same breach is still ongoing', function (): void {
    $rule = AlertRule::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'RTO spike',
        'metric' => 'rto_rate_by_state',
        'operator' => 'gt',
        'threshold' => 25,
        'window_days' => 7,
    ]);

    $evaluator = app(RuleEvaluator::class);

    expect($evaluator->evaluate($rule))->toHaveCount(1);
    // Alert fatigue is the failure mode; a standing breach must stay quiet.
    expect($evaluator->evaluate($rule))->toBeEmpty();
    expect(AlertEvent::query()->count())->toBe(1);
});

it('ignores states with too little volume to be meaningful', function (): void {
    // Five orders, all RTO — 100%, but far too thin to alert on.
    makeOrders($this, 'Goa', 5, 5);
    app(RebuildStateRollup::class)->handle($this->tenant, CarbonImmutable::now()->subDays(30), CarbonImmutable::now());

    $readings = collect(app(MetricResolver::class)->resolve('rto_rate_by_state', 7));

    expect($readings->pluck('dimension'))->not->toContain('Goa')
        ->and($readings->pluck('dimension'))->toContain('Bihar');
});

it('honours the scope so a rule can watch only chosen states', function (): void {
    $rule = AlertRule::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Karnataka only',
        'metric' => 'rto_rate_by_state',
        'operator' => 'gt',
        'threshold' => 25,
        'window_days' => 7,
        'scope' => 'state',
        'scope_values' => ['Karnataka'],
    ]);

    // Bihar breaches but is out of scope; Karnataka is in scope but fine.
    expect(app(RuleEvaluator::class)->evaluate($rule))->toBeEmpty();
});

it('tests a rule against live data without saving an event', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/alerts/test', [
        'name' => 'Draft rule',
        'metric' => 'rto_rate_by_state',
        'operator' => 'gt',
        'threshold' => 25,
        'window_days' => 7,
    ]);

    $response->assertOk()->assertJsonPath('data.would_fire', true);
    expect($response->json('data.preview.0.title'))->toContain('Bihar');

    // A dry run must leave no trace.
    expect(AlertEvent::query()->count())->toBe(0)
        ->and(AlertRule::query()->count())->toBe(0);
});

it('rejects a metric that is not in the catalogue', function (): void {
    $this->actingAs($this->user)->postJson('/api/alerts/rules', [
        'name' => 'Nonsense',
        'metric' => 'drop_table_orders',
        'operator' => 'gt',
        'threshold' => 1,
        'window_days' => 7,
    ])->assertStatus(422);
});

it('will not let a role without manage permission create rules', function (): void {
    $viewer = $this->userFor($this->tenant, ['alerts.rules.view', 'alerts.events.view'], 'ANALYST');

    $this->actingAs($viewer)->getJson('/api/alerts/rules')->assertOk();
    $this->actingAs($viewer)->postJson('/api/alerts/rules', [
        'name' => 'Nope', 'metric' => 'return_rate', 'operator' => 'gt', 'threshold' => 10, 'window_days' => 7,
    ])->assertForbidden();
});

it('surfaces events with an unread count and can mark them read', function (): void {
    $rule = AlertRule::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'RTO', 'metric' => 'rto_rate_by_state',
        'operator' => 'gt', 'threshold' => 25, 'window_days' => 7,
    ]);
    app(RuleEvaluator::class)->evaluate($rule);

    $this->actingAs($this->user)->getJson('/api/alerts/events')
        ->assertOk()
        ->assertJsonPath('data.unread', 1);

    $this->actingAs($this->user)->postJson('/api/alerts/events/read')->assertOk();

    $this->actingAs($this->user)->getJson('/api/alerts/events')->assertJsonPath('data.unread', 0);
});

it('offers a delivery channel only once it is configured', function (): void {
    $channels = collect($this->actingAs($this->user)->getJson('/api/alerts/schema')->json('data.channels'));

    // Nothing is set up yet, so only the in-app bell can actually deliver.
    expect($channels->firstWhere('key', 'in_app')['available'])->toBeTrue()
        ->and($channels->firstWhere('key', 'slack')['available'])->toBeFalse()
        ->and($channels->firstWhere('key', 'slack')['note'])->toContain('Admin → Settings')
        ->and($channels->firstWhere('key', 'whatsapp')['available'])->toBeFalse();

    NotificationSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/xyz',
    ]);

    $channels = collect($this->actingAs($this->user)->getJson('/api/alerts/schema')->json('data.channels'));

    expect($channels->firstWhere('key', 'slack')['available'])->toBeTrue()
        ->and($channels->firstWhere('key', 'slack')['note'])->toBeNull();
});
