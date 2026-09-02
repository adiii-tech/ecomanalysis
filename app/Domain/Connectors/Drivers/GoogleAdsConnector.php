<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers;

use App\Domain\Connectors\Contracts\SupportsOAuth;
use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Domain\Connectors\Support\AbstractConnector;
use App\Domain\Connectors\Support\AuthorizesWithGoogle;
use App\Domain\Connectors\Support\GoogleOAuth;
use App\Enums\AuthType;
use App\Models\AdAccount;
use App\Models\Campaign;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Ads API via GAQL search-stream. Micros are converted to paise at the
 * boundary so nothing downstream ever sees a float.
 */
class GoogleAdsConnector extends AbstractConnector implements SupportsOAuth
{
    use AuthorizesWithGoogle;

    private const API_VERSION = 'v18';

    public function __construct(private readonly GoogleOAuth $oauth) {}

    public function id(): string
    {
        return 'google_ads';
    }

    public function label(): string
    {
        return 'Google Ads';
    }

    public function summary(): string
    {
        return 'Campaign spend, clicks, conversions, conversion value and geographic performance from your Google Ads account.';
    }

    public function authType(): AuthType
    {
        return AuthType::OAuth;
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function credentialFields(): array
    {
        return [
            'customer_id' => ['label' => 'Customer ID', 'type' => 'text', 'required' => true, 'help' => '1234567890 (no dashes)'],
            'login_customer_id' => ['label' => 'Manager (MCC) ID', 'type' => 'text', 'required' => false],
            'developer_token' => ['label' => 'Developer token', 'type' => 'password', 'required' => true],
            'client_id' => ['label' => 'OAuth client ID', 'type' => 'text', 'required' => true],
            'client_secret' => ['label' => 'OAuth client secret', 'type' => 'password', 'required' => true],
            'refresh_token' => ['label' => 'Refresh token', 'type' => 'password', 'required' => true],
        ];
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['campaigns', 'insights', 'geo'];
    }

    /** @return array<string, int> */
    public function syncCadence(): array
    {
        return ['campaigns' => 180, 'insights' => 60, 'geo' => 360];
    }

    /** @return list<string> */
    public function oauthScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/adwords',
            'https://www.googleapis.com/auth/userinfo.email',
        ];
    }

    /** @return array<string, string> */
    public function pendingSelections(): array
    {
        return ['customer_id' => 'Google Ads account'];
    }

    /** @return list<array{id: string, label: string, meta?: string}> */
    public function availableResources(string $key): array
    {
        if ($key !== 'customer_id' || blank(config('services.google.ads_developer_token'))) {
            return [];
        }

        $headers = [
            'Authorization' => 'Bearer '.$this->googleAccessToken(),
            'developer-token' => (string) config('services.google.ads_developer_token'),
        ];

        if (filled(config('services.google.ads_login_customer_id'))) {
            $headers['login-customer-id'] = (string) config('services.google.ads_login_customer_id');
        }

        $accessible = Http::withHeaders($headers)
            ->timeout(20)
            ->get('https://googleads.googleapis.com/'.self::API_VERSION.'/customers:listAccessibleCustomers');

        return collect($accessible->json('resourceNames', []))
            ->map(static function (string $resource): array {
                $id = str_replace('customers/', '', $resource);

                $padded = str_pad($id, 10, '0', STR_PAD_LEFT);

                return [
                    'id' => $id,
                    // Formatted the way Google shows it, e.g. 123-456-7890.
                    'label' => substr($padded, 0, 3).'-'.substr($padded, 3, 3).'-'.substr($padded, 6, 4),
                    'meta' => 'Customer '.$id,
                ];
            })
            ->values()
            ->all();
    }

    public function testConnection(): HealthResult
    {
        try {
            $rows = $this->query('SELECT customer.id, customer.descriptive_name, customer.currency_code FROM customer LIMIT 1');
        } catch (Throwable $e) {
            return HealthResult::fail('Google Ads unreachable: '.$e->getMessage());
        }

        if ($rows === []) {
            return HealthResult::fail('Google Ads returned no customer for those credentials.');
        }

        return HealthResult::ok('Connected to '.data_get($rows[0], 'customer.descriptiveName', 'Google Ads').'.', [
            'currency' => data_get($rows[0], 'customer.currencyCode'),
        ]);
    }

    protected function syncCampaigns(SyncContext $ctx): SyncReport
    {
        $rows = $this->query(<<<'GAQL'
            SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type,
                   campaign_budget.amount_micros, campaign.start_date, campaign.end_date
            FROM campaign
        GAQL);

        $account = AdAccount::query()->updateOrCreate(
            ['tenant_id' => $ctx->tenant->id, 'platform' => 'google_ads', 'external_id' => $this->customerId()],
            ['name' => 'Google Ads '.$this->customerId(), 'currency' => 'INR', 'status' => 'active'],
        );

        $fetched = 0;
        foreach ($rows as $row) {
            $fetched++;
            Campaign::query()->updateOrCreate(
                ['tenant_id' => $ctx->tenant->id, 'platform' => 'google_ads', 'external_id' => (string) data_get($row, 'campaign.id')],
                [
                    'ad_account_id' => $account->id,
                    'name' => (string) data_get($row, 'campaign.name'),
                    'objective' => strtolower((string) data_get($row, 'campaign.advertisingChannelType', '')),
                    'status' => strtolower((string) data_get($row, 'campaign.status', 'enabled')),
                    'daily_budget' => $this->microsToPaise(data_get($row, 'campaignBudget.amountMicros', 0)),
                    'started_at' => data_get($row, 'campaign.startDate'),
                    'stopped_at' => data_get($row, 'campaign.endDate'),
                ],
            );
        }

        return SyncReport::of('campaigns', $fetched, $fetched, now()->toIso8601String());
    }

    protected function syncInsights(SyncContext $ctx): SyncReport
    {
        return $this->pull($ctx, 'insights', 'total', <<<GAQL
            SELECT segments.date, campaign.id, metrics.cost_micros, metrics.impressions, metrics.clicks,
                   metrics.conversions, metrics.conversions_value
            FROM campaign
            WHERE segments.date BETWEEN '{$this->since($ctx)}' AND '{$this->until($ctx)}'
        GAQL);
    }

    protected function syncGeo(SyncContext $ctx): SyncReport
    {
        return $this->pull($ctx, 'geo', 'geo', <<<GAQL
            SELECT segments.date, campaign.id, segments.geo_target_region, metrics.cost_micros,
                   metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value
            FROM geographic_view
            WHERE segments.date BETWEEN '{$this->since($ctx)}' AND '{$this->until($ctx)}'
        GAQL);
    }

    private function pull(SyncContext $ctx, string $entity, string $breakdownKey, string $gaql): SyncReport
    {
        try {
            $rows = $this->query($gaql);
        } catch (Throwable $e) {
            return SyncReport::failed($entity, $e->getMessage());
        }

        $campaigns = Campaign::query()
            ->where('tenant_id', $ctx->tenant->id)
            ->where('platform', 'google_ads')
            ->pluck('id', 'external_id');

        $insertRows = [];
        foreach ($rows as $row) {
            $insertRows[] = [
                'tenant_id' => $ctx->tenant->id,
                'date' => data_get($row, 'segments.date'),
                'platform' => 'google_ads',
                'campaign_id' => $campaigns[(string) data_get($row, 'campaign.id')] ?? null,
                'breakdown_key' => $breakdownKey,
                'state' => data_get($row, 'segments.geoTargetRegion'),
                'spend' => $this->microsToPaise(data_get($row, 'metrics.costMicros', 0)),
                'impressions' => (int) data_get($row, 'metrics.impressions', 0),
                'clicks' => (int) data_get($row, 'metrics.clicks', 0),
                'conversions' => (int) round((float) data_get($row, 'metrics.conversions', 0)),
                'conversion_value' => (int) round(((float) data_get($row, 'metrics.conversionsValue', 0)) * 100),
            ];
        }

        $upserted = $this->upsertMetrics('ad_insights_daily', $insertRows, ['date', 'platform', 'breakdown_key', 'campaign_id', 'ad_set_id', 'ad_id', 'placement', 'age', 'gender', 'country', 'state'], ['spend', 'impressions', 'clicks', 'reach', 'conversions', 'conversion_value', 'add_to_carts', 'checkouts', 'video_views_3s', 'video_views_thruplay']);

        return SyncReport::of($entity, count($insertRows), $upserted, $this->until($ctx));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function query(string $gaql): array
    {
        $response = $this->client()->post($this->customerId().'/googleAds:searchStream', ['query' => $gaql]);

        if ($response->failed()) {
            throw new \RuntimeException((string) ($response->json('0.error.message') ?? 'Google Ads HTTP '.$response->status()));
        }

        $results = [];
        foreach ($response->json() ?? [] as $batch) {
            $results = [...$results, ...($batch['results'] ?? [])];
        }

        return $results;
    }

    private function client(): PendingRequest
    {
        $token = $this->oauth->accessToken(
            (string) $this->credential('client_id'),
            (string) $this->credential('client_secret'),
            (string) $this->credential('refresh_token'),
        );

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'developer-token' => (string) $this->credential('developer_token'),
        ];

        if (filled($this->credential('login_customer_id'))) {
            $headers['login-customer-id'] = (string) $this->credential('login_customer_id');
        }

        return $this->http('https://googleads.googleapis.com/'.self::API_VERSION.'/customers/', $headers);
    }

    private function customerId(): string
    {
        return preg_replace('/\D/', '', (string) $this->credential('customer_id')) ?? '';
    }

    private function microsToPaise(mixed $micros): int
    {
        return (int) round(((int) $micros) / 10_000);
    }

    private function since(SyncContext $ctx): string
    {
        return $ctx->sinceOrDefault(90)->toDateString();
    }

    private function until(SyncContext $ctx): string
    {
        return $ctx->untilOrNow()->toDateString();
    }
}
