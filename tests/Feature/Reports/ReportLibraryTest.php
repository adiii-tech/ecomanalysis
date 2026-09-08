<?php

declare(strict_types=1);

use App\Domain\Access\PermissionRegistry;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Queries\PnlQuery;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportRegistry;
use App\Domain\Rollups\Actions\RebuildCohorts;
use App\Domain\Rollups\Actions\RebuildCustomerMetrics;
use App\Domain\Rollups\Actions\RebuildDailyMetrics;
use App\Domain\Rollups\Actions\RebuildSkuRollup;
use App\Domain\Rollups\Actions\RebuildStateRollup;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Channel;
use App\Models\CostSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReportFavourite;
use App\Models\ReportSchedule;
use App\Models\ReportShare;
use App\Models\Sku;
use App\Support\Money;
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
        'monthly_fixed_opex' => Money::fromRupees(30000),
    ]);

    $channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'v1',
        'sku_code' => 'SKU-1', 'name' => 'Indigo Kurta', 'category' => 'Ethnic Wear',
        'mrp' => 150000, 'selling_price' => 100000, 'cost_price' => 40000, 'hsn' => '6204', 'gst_rate' => 5,
    ]);

    $customer = Customer::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'c-1',
        'name' => 'Asha Rao', 'city' => 'Mumbai', 'state' => 'Maharashtra',
    ]);

    foreach ([2, 5, 9] as $index => $daysAgo) {
        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'customer_id' => $customer->id,
            'source' => 'test',
            'external_id' => 'o-'.$index,
            'order_number' => '#400'.$index,
            'placed_at' => CarbonImmutable::now()->subDays($daysAgo),
            'status' => OrderStatus::Delivered,
            'payment_mode' => $index === 0 ? PaymentMode::Cod : PaymentMode::Prepaid,
            'shipping_state' => 'Maharashtra',
            'shipping_city' => 'Mumbai',
            'is_first_order' => $index === 2,
            'discount_codes' => $index === 1 ? 'WELCOME10' : null,
        ]);

        OrderItem::query()->create([
            'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $sku->id,
            'sku_code' => 'SKU-1', 'qty' => 2, 'unit_price' => 100000, 'discount' => 10000,
            'tax' => 4500, 'cogs_unit' => 40000,
        ]);

        app(ComputeOrderEconomics::class)->handle($order->fresh());
    }

    $from = CarbonImmutable::now()->subDays(40);
    $to = CarbonImmutable::now();

    app(RebuildDailyMetrics::class)->handle($this->tenant, $from, $to);
    app(RebuildSkuRollup::class)->handle($this->tenant, $from, $to);
    app(RebuildStateRollup::class)->handle($this->tenant, $from, $to);
    app(RebuildCustomerMetrics::class)->handle($this->tenant);
    app(RebuildCohorts::class)->handle($this->tenant);

    $this->user = $this->userFor($this->tenant);
});

it('builds every report in the library without error', function (): void {
    $filters = WidgetFilters::forSnapshot(Period::fromPreset('last_30_days', $this->tenant->timezone));

    foreach (app(ReportRegistry::class)->all() as $slug => $report) {
        /** @var Report $report */
        $payload = $report->build($filters)->toArray();

        expect($payload)->toHaveKeys(['kpis', 'sections', 'verdict', 'caveats'])
            ->and($payload['kpis'])->not->toBeEmpty("{$slug} returned no KPIs")
            ->and($payload['sections'])->not->toBeEmpty("{$slug} returned no sections");

        // Every widget must give a judgement, not just a number.
        expect($payload['verdict'])->not->toBeNull("{$slug} returned no verdict");
        expect($payload['verdict']['headline'])->not->toBe('');
    }
});

it('gives every report a unique slug, key and permission that exists', function (): void {
    $reports = app(ReportRegistry::class)->all();
    $permissions = PermissionRegistry::all();

    expect($reports)->toHaveCount(27);

    foreach ($reports as $slug => $report) {
        expect($slug)->toBe($report->slug());
        expect(in_array($report->permission(), $permissions, true))
            ->toBeTrue("{$slug} has no matching permission");

        foreach ($report->exports() as $dataset) {
            expect(DatasetRegistry::has($dataset))
                ->toBeTrue("{$slug} exports unknown dataset {$dataset}");
        }
    }
});

it('lists only the reports a user may open', function (): void {
    $limited = $this->userFor($this->tenant, ['reports.library.view', 'reports.pnl_statement.view'], 'ANALYST');

    $response = $this->actingAs($limited)->getJson('/api/reports');
    $response->assertOk();

    $slugs = collect($response->json('data.reports'))->pluck('slug');

    expect($slugs)->toContain('pnl-statement')
        ->and($slugs)->not->toContain('gst-summary')
        ->and($slugs)->toHaveCount(1);
});

it('refuses a report the user cannot open, and 404s an unknown one', function (): void {
    $limited = $this->userFor($this->tenant, ['reports.library.view'], 'ANALYST');

    $this->actingAs($limited)->getJson('/api/reports/gst-summary')->assertForbidden();
    $this->actingAs($this->user)->getJson('/api/reports/not-a-report')->assertNotFound();
});

it('returns a full payload for a report', function (): void {
    $response = $this->actingAs($this->user)->getJson('/api/reports/channel-scorecard?preset=last_30_days');

    $response->assertOk()
        ->assertJsonPath('data.report.slug', 'channel-scorecard')
        ->assertJsonStructure(['data' => ['report', 'kpis', 'sections', 'verdict', 'caveats']]);

    $table = collect($response->json('data.sections'))->firstWhere('type', 'table');

    expect($table['rows'])->not->toBeEmpty()
        ->and($table['columns'])->not->toBeEmpty()
        // Money must still be paise on the wire; the UI formats at the edge.
        ->and($table['rows'][0]['net_sales'])->toBeInt();
});

it('records that a report was opened, without favouriting it', function (): void {
    $this->actingAs($this->user)->getJson('/api/reports/channel-scorecard')->assertOk();

    $record = ReportFavourite::query()->where('report_key', 'channel_scorecard')->first();

    expect($record->last_used_at)->not->toBeNull()
        ->and($record->is_favourite)->toBeFalse();
});

it('toggles a favourite on and off', function (): void {
    $this->actingAs($this->user)->postJson('/api/reports/channel-scorecard/favourite')
        ->assertOk()->assertJsonPath('data.is_favourite', true);

    $this->actingAs($this->user)->postJson('/api/reports/channel-scorecard/favourite')
        ->assertOk()->assertJsonPath('data.is_favourite', false);
});

it('creates a share link that renders for a signed-out visitor', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/reports/channel-scorecard/share?preset=last_30_days', [
        'expires_in_days' => 7,
    ]);

    $response->assertOk();
    $url = $response->json('data.url');

    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/shared')
            ->where('expired', false)
            ->where('report.slug', 'channel-scorecard')
            ->has('payload.sections'));

    expect(ReportShare::query()->first()->view_count)->toBe(1);
});

it('freezes the filters into the share so the recipient sees the same window', function (): void {
    $this->actingAs($this->user)->postJson('/api/reports/channel-scorecard/share?from=2026-01-01&to=2026-01-31');

    $share = ReportShare::query()->first();

    expect($share->filters['from'])->toBe('2026-01-01')
        ->and($share->filters['to'])->toBe('2026-01-31');
});

it('stops serving a revoked or expired share', function (): void {
    $url = $this->actingAs($this->user)->postJson('/api/reports/channel-scorecard/share')->json('data.url');
    $share = ReportShare::query()->first();

    $this->actingAs($this->user)->deleteJson("/api/reports/channel-scorecard/shares/{$share->id}")->assertOk();

    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('expired', true)->where('payload', null));

    $share->forceFill(['revoked_at' => null, 'expires_at' => now()->subDay()])->save();

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('expired', true));
});

it('will not let a user share a report they cannot open', function (): void {
    $limited = $this->userFor($this->tenant, ['reports.library.view'], 'ANALYST');

    $this->actingAs($limited)->postJson('/api/reports/gst-summary/share')->assertForbidden();
});

it('saves, lists and deletes a scheduled delivery', function (): void {
    $this->actingAs($this->user)->postJson('/api/reports/pnl-statement/schedules', [
        'cadence' => 'weekly',
        'hour' => 8,
        'day_of_week' => 1,
        'recipients' => ['founder@brand.test', 'ca@brand.test'],
        'format' => 'pdf',
    ])->assertOk();

    $rows = $this->actingAs($this->user)->getJson('/api/reports/pnl-statement/schedules')->json('data.rows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['recipients'])->toBe(['founder@brand.test', 'ca@brand.test']);

    $this->actingAs($this->user)->deleteJson("/api/reports/pnl-statement/schedules/{$rows[0]['id']}")->assertOk();

    expect(ReportSchedule::query()->count())->toBe(0);
});

it('rejects a schedule with no valid recipient', function (): void {
    $this->actingAs($this->user)->postJson('/api/reports/pnl-statement/schedules', [
        'cadence' => 'weekly', 'hour' => 8, 'recipients' => ['not-an-email'], 'format' => 'pdf',
    ])->assertStatus(422);
});

it('keeps a P&L month column inside the selected window and pro-rates fixed opex', function (): void {
    // A window covering only part of a month must not charge that whole month's
    // fixed cost, or every short report looks loss-making.
    $timezone = $this->tenant->timezone;
    $start = CarbonImmutable::now($timezone)->startOfMonth();
    $filters = WidgetFilters::forSnapshot(Period::make(
        $start->toDateString(),
        $start->addDays(9)->toDateString(),
        $timezone,
    ));

    $result = app(PnlQuery::class)->handle($filters);
    $column = $result['columns'][0];

    $daysInMonth = $start->daysInMonth;
    $expected = (int) round(Money::fromRupees(30000) * (10 / $daysInMonth));

    expect($result['columns'])->toHaveCount(1)
        ->and(-$column['fixed_opex'])->toBe($expected)
        ->and($column['month'])->toContain('10 of '.$daysInMonth.' days');
});

it('never exposes another tenant through a share link', function (): void {
    $url = $this->actingAs($this->user)->postJson('/api/reports/channel-scorecard/share')->json('data.url');

    $other = $this->tenant(['name' => 'Rival Brand']);
    Channel::query()->create([
        'tenant_id' => $other->id, 'name' => 'Rival Shopify', 'code' => 'rival', 'type' => ChannelType::D2c,
    ]);

    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $response = $this->get($url);
    $response->assertOk();

    expect(json_encode($response->viewData('page')['props']))->not->toContain('Rival Shopify');
});
