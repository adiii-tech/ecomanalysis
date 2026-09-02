<?php

declare(strict_types=1);

use App\Enums\Plan;
use App\Models\AlertRule;
use App\Models\Connector;
use App\Support\TenantContext;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);
});

function onPlan(Plan $plan): void
{
    test()->tenant->forceFill(['plan' => $plan])->save();
    app(TenantContext::class)->set(test()->tenant->fresh());
    Feature::flushCache();
}

it('reads plan limits straight from the plan, with nothing stored', function (): void {
    onPlan(Plan::Starter);
    expect(Feature::value('history-days'))->toBe(180)
        ->and(Feature::active('advanced-reports'))->toBeFalse();

    onPlan(Plan::Growth);
    expect(Feature::value('history-days'))->toBe(730)
        ->and(Feature::active('advanced-reports'))->toBeTrue();
});

it('clamps a date range to the plan history window and says so', function (): void {
    onPlan(Plan::Starter);

    $response = $this->actingAs($this->user)->getJson('/api/dashboard/kpis?from=2020-01-01&to='.now()->toDateString());

    $response->assertOk();

    $clamp = $response->json('meta.history_clamped');

    expect($clamp)->not->toBeNull()
        ->and($clamp['history_days'])->toBe(180)
        ->and($clamp['requested_from'])->toBe('2020-01-01')
        ->and($response->json('meta.period.from'))->toBe($clamp['allowed_from']);
});

it('leaves a range inside the window alone', function (): void {
    onPlan(Plan::Growth);

    $response = $this->actingAs($this->user)->getJson('/api/dashboard/kpis?preset=last_30_days');

    expect($response->json('meta.history_clamped'))->toBeNull();
});

it('refuses to connect past the plan connector limit', function (): void {
    onPlan(Plan::Starter);

    foreach (['shopify', 'meta', 'ga4'] as $connector) {
        Connector::query()->create([
            'tenant_id' => $this->tenant->id,
            'connector_id' => $connector,
            'status' => 'connected',
            'credentials' => [],
        ]);
    }

    $response = $this->actingAs($this->user)->postJson('/api/connectors/google_ads/connect', []);

    $response->assertStatus(422)->assertJsonPath('meta.upgrade_required', true);
    expect($response->json('message'))->toContain('3 connectors');
});

it('lets a connected source be reconnected even at the limit', function (): void {
    onPlan(Plan::Starter);

    foreach (['shopify', 'meta', 'ga4'] as $connector) {
        Connector::query()->create([
            'tenant_id' => $this->tenant->id, 'connector_id' => $connector, 'status' => 'connected', 'credentials' => [],
        ]);
    }

    // Reconnecting an existing source is not a new seat, so this must not be
    // blocked — it fails later on credentials instead.
    $response = $this->actingAs($this->user)->postJson('/api/connectors/shopify/connect', []);

    expect($response->json('meta.upgrade_required'))->toBeNull();
});

it('refuses a new alert rule past the plan limit', function (): void {
    onPlan(Plan::Starter);

    for ($index = 0; $index < 5; $index++) {
        AlertRule::query()->create([
            'tenant_id' => $this->tenant->id, 'created_by' => $this->user->id,
            'name' => 'Rule '.$index, 'metric' => 'rto_rate', 'operator' => 'gt',
            'threshold' => 10, 'window_days' => 7, 'channels' => ['in_app'],
        ]);
    }

    $this->actingAs($this->user)->postJson('/api/alerts/rules', [
        'name' => 'One too many', 'metric' => 'rto_rate', 'operator' => 'gt',
        'threshold' => 10, 'window_days' => 7, 'channels' => ['in_app'],
    ])->assertStatus(422)->assertJsonPath('meta.upgrade_required', true);
});

it('has no alerts at all on the demo plan', function (): void {
    onPlan(Plan::Demo);

    $this->actingAs($this->user)->postJson('/api/alerts/rules', [
        'name' => 'Any rule', 'metric' => 'rto_rate', 'operator' => 'gt',
        'threshold' => 10, 'window_days' => 7, 'channels' => ['in_app'],
    ])->assertStatus(422);

    expect(AlertRule::query()->count())->toBe(0);
});

it('shows a restricted report in the library but will not open it', function (): void {
    onPlan(Plan::Starter);

    $reports = collect($this->actingAs($this->user)->getJson('/api/reports')->json('data.reports'));
    $pnl = $reports->firstWhere('slug', 'pnl-statement');

    // Visible, so the user knows what an upgrade buys.
    expect($pnl['requires_upgrade'])->toBeTrue()
        ->and($reports->firstWhere('slug', 'channel-scorecard')['requires_upgrade'])->toBeFalse();

    $this->actingAs($this->user)->getJson('/api/reports/pnl-statement')
        ->assertForbidden()
        ->assertJsonPath('meta.upgrade_required', true);
});

it('opens every report once the plan includes them', function (): void {
    onPlan(Plan::Growth);

    $this->actingAs($this->user)->getJson('/api/reports/pnl-statement')->assertOk();
});

it('keeps share links and scheduled delivery off the demo plan', function (): void {
    onPlan(Plan::Demo);

    $this->actingAs($this->user)->postJson('/api/reports/channel-scorecard/share')
        ->assertForbidden()->assertJsonPath('meta.upgrade_required', true);

    $this->actingAs($this->user)->postJson('/api/reports/channel-scorecard/schedules', [
        'cadence' => 'weekly', 'hour' => 8, 'recipients' => ['a@b.test'], 'format' => 'pdf',
    ])->assertForbidden();
});
