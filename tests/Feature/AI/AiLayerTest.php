<?php

declare(strict_types=1);

use App\Domain\AI\Tools\GetKpisTool;
use App\Domain\AI\Tools\ToolRegistry;
use App\Domain\Rollups\Actions\RebuildDailyMetrics;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\AiChatSession;
use App\Models\AiInsightCache;
use App\Models\AiUsageLog;
use App\Models\Channel;
use App\Models\CostSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use App\Support\MetricCache;
use App\Support\Period;
use App\Support\WidgetFilters;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    CostSetting::query()->create(['tenant_id' => $this->tenant->id, 'default_shipping_cost' => 8000]);

    $channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'v1',
        'sku_code' => 'SKU-1', 'name' => 'Indigo Kurta', 'selling_price' => 100000, 'cost_price' => 40000,
    ]);

    $order = Order::query()->create([
        'tenant_id' => $this->tenant->id, 'channel_id' => $channel->id, 'source' => 'test',
        'external_id' => 'o-1', 'order_number' => '#5001',
        'placed_at' => CarbonImmutable::now()->subDays(2),
        'status' => OrderStatus::Delivered, 'payment_mode' => PaymentMode::Prepaid,
        'shipping_state' => 'Maharashtra',
    ]);

    OrderItem::query()->create([
        'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $sku->id,
        'sku_code' => 'SKU-1', 'qty' => 2, 'unit_price' => 100000, 'cogs_unit' => 40000,
    ]);

    app(ComputeOrderEconomics::class)->handle($order->fresh());
    app(RebuildDailyMetrics::class)->handle($this->tenant, CarbonImmutable::now()->subDays(60), CarbonImmutable::now());
});

it('hides tools the user has no permission for', function (): void {
    $registry = app(ToolRegistry::class);

    $owner = $this->userFor($this->tenant);
    $opsOnly = $this->userFor($this->tenant, ['operations.returns_kpis.view'], 'OPERATIONS');

    $ownerTools = $registry->forUser($owner)->map(fn ($t) => $t->name());
    $opsTools = $registry->forUser($opsOnly)->map(fn ($t) => $t->name());

    expect($ownerTools)->toContain('get_kpis', 'get_campaigns', 'get_customers')
        // The agent cannot read around RBAC — the tool is not offered at all.
        ->and($opsTools)->toContain('get_returns_and_rto')
        ->and($opsTools)->not->toContain('get_kpis')
        ->and($opsTools)->not->toContain('get_campaigns');
});

it('refuses to execute a tool the user cannot see, even if the model asks for it by name', function (): void {
    $registry = app(ToolRegistry::class);
    $opsOnly = $this->userFor($this->tenant, ['operations.returns_kpis.view'], 'OPERATIONS');

    expect($registry->find($opsOnly, 'get_campaigns'))->toBeNull()
        ->and($registry->find($opsOnly, 'get_returns_and_rto'))->not->toBeNull();
});

it('returns real numbers from the rollups, formatted as rupees not paise', function (): void {
    $filters = new WidgetFilters(Period::fromPreset('last_30_days'));
    $result = app(GetKpisTool::class)->run([], $filters);

    // 2 x ₹1000 net, less ₹800 COGS, ₹80 shipping and a 2% (₹40) gateway fee.
    expect($result['current']['net_sales'])->toBe('₹2,000')
        ->and($result['current']['cogs'])->toBe('₹800')
        ->and($result['current']['orders'])->toBe(1)
        ->and($result['current']['contribution_margin'])->toBe('₹1,080')
        // A model shown raw paise would call ₹2,000 "two hundred thousand".
        ->and($result['current']['net_sales'])->not->toContain('200000');
});

it('lets the model narrow the period and channel through tool params', function (): void {
    $filters = new WidgetFilters(Period::fromPreset('last_30_days'));

    $lastMonth = app(GetKpisTool::class)->run(['period' => 'last_month'], $filters);
    $today = app(GetKpisTool::class)->run(['period' => 'today'], $filters);

    expect($lastMonth['period'])->not->toBe($today['period'])
        // The order was two days ago, so today's window must be empty.
        ->and($today['current']['orders'])->toBe(0);
});

it('exposes tool descriptions and suggested prompts to the UI', function (): void {
    $user = $this->userFor($this->tenant);

    $response = $this->actingAs($user)->getJson('/api/ai/status');

    $response->assertOk()
        ->assertJsonPath('data.model', 'claude-opus-5')
        ->assertJsonStructure(['data' => ['configured', 'model', 'credits', 'tools', 'suggested_prompts']]);

    expect($response->json('data.suggested_prompts'))->toHaveCount(4);
});

it('says AI is off rather than failing when no key is configured', function (): void {
    config(['ai.api_key' => null]);
    $user = $this->userFor($this->tenant);

    $this->actingAs($user)->getJson('/api/ai/status')
        ->assertOk()
        ->assertJsonPath('data.configured', false)
        ->assertJsonPath('data.caveat', 'No Anthropic API key is configured on this server, so AI features are switched off. An admin needs to add ANTHROPIC_API_KEY.');

    $this->actingAs($user)->postJson('/api/ai/ask/chat', ['message' => 'How are sales?'])
        ->assertStatus(503);
});

it('blocks a chat once credits are exhausted, and does not charge for the refusal', function (): void {
    config(['ai.api_key' => 'test-key']);

    $user = $this->userFor($this->tenant);
    $user->forceFill(['ai_credits_used' => 200, 'ai_credit_limit' => 200])->save();

    $this->actingAs($user->fresh())
        ->postJson('/api/ai/ask/chat', ['message' => 'How are sales?'])
        ->assertStatus(402)
        ->assertJsonPath('meta.credits.remaining', 0);

    expect(AiUsageLog::query()->count())->toBe(0);
});

it('creates, renames, shares and revokes a chat session', function (): void {
    $user = $this->userFor($this->tenant);

    $session = AiChatSession::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $user->id,
        'title' => 'Sales question', 'last_message_at' => now(),
    ]);

    $this->actingAs($user)->patchJson("/api/ai/ask/session/{$session->id}", ['title' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.title', 'Renamed');

    $shared = $this->actingAs($user)->postJson("/api/ai/ask/session/{$session->id}/share");
    $shared->assertOk();
    $token = $shared->json('data.share_token');

    expect($token)->not->toBeNull();

    // The public link works without a session.
    $this->get("/ask-ai/shared/{$token}")->assertOk();

    $this->actingAs($user)->postJson("/api/ai/ask/session/{$session->id}/share", ['revoke' => true])
        ->assertOk()->assertJsonPath('data.share_token', null);

    // Revoking kills the link immediately.
    $this->get("/ask-ai/shared/{$token}")->assertNotFound();
});

it('will not let one user read another user chat', function (): void {
    $owner = $this->userFor($this->tenant);
    $other = $this->userFor($this->tenant);

    $session = AiChatSession::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $owner->id, 'title' => 'Private', 'last_message_at' => now(),
    ]);

    $this->actingAs($other)->getJson("/api/ai/ask/session/{$session->id}")->assertNotFound();
});

it('refuses a chart insight for a widget the user cannot view', function (): void {
    config(['ai.api_key' => 'test-key']);

    $limited = $this->userFor($this->tenant, ['ai.chart_insight.view'], 'ANALYST');

    $this->actingAs($limited)->postJson('/api/ai/chart-insight', [
        'widget_key' => 'finance.pnl',
        'title' => 'P&L',
        'payload' => ['net_sales' => 1000],
    ])->assertForbidden();
});

it('serves a cached insight without spending a credit', function (): void {
    config(['ai.api_key' => 'test-key']);

    $user = $this->userFor($this->tenant);
    $filters = new WidgetFilters(Period::fromPreset('last_30_days'));
    $key = app(MetricCache::class)->key($this->tenant->id, 'insight', 'dashboard.kpi_strip', $filters->cacheKey());

    AiInsightCache::query()->create([
        'tenant_id' => $this->tenant->id,
        'cache_key' => $key,
        'widget_key' => 'dashboard.kpi_strip',
        'content' => 'Net sales are up because prepaid orders grew.',
        'expires_at' => now()->addHours(12),
    ]);

    $before = $user->ai_credits_used;

    $this->actingAs($user)->postJson('/api/ai/chart-insight?preset=last_30_days', [
        'widget_key' => 'dashboard.kpi_strip',
        'title' => 'KPI strip',
        'payload' => ['net_sales' => 200000],
    ])->assertOk()
        ->assertJsonPath('data.cached', true)
        ->assertJsonPath('data.content', 'Net sales are up because prepaid orders grew.');

    expect($user->fresh()->ai_credits_used)->toBe($before);
});
