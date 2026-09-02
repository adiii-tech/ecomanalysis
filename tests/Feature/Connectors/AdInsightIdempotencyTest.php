<?php

declare(strict_types=1);

use App\Domain\Connectors\Actions\ConnectConnector;
use App\Domain\Connectors\Actions\RunConnectorSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->tenant = $this->tenant();

    Http::fake([
        // Account lookup used by connect/testConnection and syncAdAccounts.
        'https://graph.facebook.com/*/act_123*fields=name*' => Http::response([
            'name' => 'Kaira Ads', 'currency' => 'INR', 'account_status' => 1,
        ]),
        'https://graph.facebook.com/*/act_123/insights*' => Http::response([
            'data' => [[
                'date_start' => '2026-08-20',
                'campaign_id' => '77',
                'adset_id' => '88',
                'ad_id' => '99',
                'spend' => '1500.50',
                'impressions' => '40000',
                'clicks' => '600',
                'reach' => '25000',
                'actions' => [['action_type' => 'purchase', 'value' => '12']],
                'action_values' => [['action_type' => 'purchase', 'value' => '48000.00']],
            ]],
            'paging' => ['cursors' => ['after' => null]],
        ]),
        'https://graph.facebook.com/*' => Http::response(['data' => [], 'paging' => []]),
    ]);

    app(ConnectConnector::class)->handle($this->tenant, 'meta', [
        'access_token' => 'token', 'ad_account_id' => 'act_123',
    ]);
});

it('does not double-count ad spend when the same day is synced twice', function (): void {
    app(RunConnectorSync::class)->handle($this->tenant, 'meta', 'insights', 'manual');
    $afterFirst = (int) DB::table('ad_insights_daily')->where('tenant_id', $this->tenant->id)->sum('spend');

    // Meta pulls a rolling window, so consecutive syncs always overlap.
    app(RunConnectorSync::class)->handle($this->tenant, 'meta', 'insights', 'manual');
    app(RunConnectorSync::class)->handle($this->tenant, 'meta', 'insights', 'manual');

    $afterThird = (int) DB::table('ad_insights_daily')->where('tenant_id', $this->tenant->id)->sum('spend');
    $rows = DB::table('ad_insights_daily')->where('tenant_id', $this->tenant->id)->count();

    expect($afterFirst)->toBe(150050)
        ->and($rows)->toBe(1)
        ->and($afterThird)->toBe($afterFirst);
});
