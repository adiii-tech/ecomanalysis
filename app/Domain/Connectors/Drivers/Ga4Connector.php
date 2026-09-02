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
use App\Models\AnalyticsRealtime;
use App\Models\Sku;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Analytics 4 Data API. Demographic reports need Google Signals; when it
 * is off GA4 returns nothing and we surface a caveat instead of guessing.
 */
class Ga4Connector extends AbstractConnector implements SupportsOAuth
{
    use AuthorizesWithGoogle;

    public function __construct(private readonly GoogleOAuth $oauth) {}

    public function id(): string
    {
        return 'ga4';
    }

    public function label(): string
    {
        return 'Google Analytics 4';
    }

    public function summary(): string
    {
        return 'Sessions, channel groups, the ecommerce funnel, product views and add-to-carts, pages, cities, realtime users and UTM performance.';
    }

    public function authType(): AuthType
    {
        return AuthType::OAuth;
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function credentialFields(): array
    {
        return [
            'property_id' => ['label' => 'GA4 property ID', 'type' => 'text', 'required' => true, 'help' => 'Numeric, e.g. 312345678'],
            'client_id' => ['label' => 'OAuth client ID', 'type' => 'text', 'required' => true],
            'client_secret' => ['label' => 'OAuth client secret', 'type' => 'password', 'required' => true],
            'refresh_token' => ['label' => 'Refresh token', 'type' => 'password', 'required' => true],
        ];
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['daily', 'pages', 'cities', 'products', 'demographics', 'realtime'];
    }

    /** @return array<string, int> */
    public function syncCadence(): array
    {
        return ['daily' => 60, 'pages' => 180, 'cities' => 180, 'products' => 180, 'demographics' => 720, 'realtime' => 5];
    }

    /** @return list<string> */
    public function oauthScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/userinfo.email',
        ];
    }

    /** @return array<string, string> */
    public function pendingSelections(): array
    {
        return ['property_id' => 'GA4 property'];
    }

    /** @return list<array{id: string, label: string, meta?: string}> */
    public function availableResources(string $key): array
    {
        if ($key !== 'property_id') {
            return [];
        }

        $response = Http::withToken($this->googleAccessToken())
            ->timeout(20)
            ->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', ['pageSize' => 200]);

        return collect($response->json('accountSummaries', []))
            ->flatMap(static fn (array $account): array => collect($account['propertySummaries'] ?? [])
                ->map(static fn (array $property): array => [
                    // The API returns "properties/123456"; the Data API wants the bare id.
                    'id' => str_replace('properties/', '', (string) $property['property']),
                    'label' => (string) ($property['displayName'] ?? $property['property']),
                    'meta' => (string) ($account['displayName'] ?? ''),
                ])->all())
            ->values()
            ->all();
    }

    public function testConnection(): HealthResult
    {
        try {
            $response = $this->client()->post($this->property().':runReport', [
                'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']],
                'metrics' => [['name' => 'sessions']],
            ]);
        } catch (Throwable $e) {
            return HealthResult::fail('GA4 unreachable: '.$e->getMessage());
        }

        return $response->successful()
            ? HealthResult::ok('GA4 property responding.', ['rows' => $response->json('rowCount', 0)])
            : HealthResult::fail('GA4 returned: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
    }

    protected function syncDaily(SyncContext $ctx): SyncReport
    {
        $rows = $this->report($ctx,
            ['date', 'sessionDefaultChannelGroup', 'sessionSource', 'sessionMedium', 'sessionCampaignName'],
            ['sessions', 'totalUsers', 'newUsers', 'engagedSessions', 'bounceRate', 'addToCarts', 'checkouts', 'transactions', 'purchaseRevenue', 'itemsViewed'],
        );

        $insert = [];
        foreach ($rows as $row) {
            $d = $row['dimensions'];
            $m = $row['metrics'];
            $insert[] = [
                'tenant_id' => $ctx->tenant->id,
                'date' => $this->date($d[0]),
                'channel_group' => $d[1] ?: 'Unassigned',
                'source' => $d[2] ?: null,
                'medium' => $d[3] ?: null,
                'campaign' => $d[4] ?: null,
                'sessions' => (int) $m[0],
                'users' => (int) $m[1],
                'new_users' => (int) $m[2],
                'engaged_sessions' => (int) $m[3],
                'bounce_rate' => round((float) $m[4] * 100, 3),
                'add_to_carts' => (int) $m[5],
                'checkouts' => (int) $m[6],
                'purchases' => (int) $m[7],
                'revenue' => (int) round(((float) $m[8]) * 100),
                'item_views' => (int) $m[9],
            ];
        }

        $upserted = $this->upsertMetrics('analytics_daily', $insert, ['date', 'channel_group', 'source', 'medium', 'campaign'], ['sessions', 'users', 'new_users', 'engaged_sessions', 'bounce_rate', 'item_views', 'add_to_carts', 'checkouts', 'purchases', 'revenue']);

        return SyncReport::of('daily', count($insert), $upserted, $ctx->untilOrNow()->toDateString());
    }

    protected function syncPages(SyncContext $ctx): SyncReport
    {
        $rows = $this->report($ctx, ['date', 'pagePath', 'pageTitle'],
            ['screenPageViews', 'totalUsers', 'eventCount', 'userEngagementDuration', 'bounceRate'], 500);

        $insert = [];
        foreach ($rows as $row) {
            [$date, $path, $title] = $row['dimensions'];
            $m = $row['metrics'];
            $users = max(1, (int) $m[1]);
            $insert[] = [
                'tenant_id' => $ctx->tenant->id,
                'date' => $this->date($date),
                'page_path' => mb_substr($path, 0, 500),
                'page_title' => $title ? mb_substr($title, 0, 190) : null,
                'views' => (int) $m[0],
                'users' => (int) $m[1],
                'events' => (int) $m[2],
                'avg_time_seconds' => round(((float) $m[3]) / $users, 2),
                'bounce_rate' => round((float) $m[4] * 100, 3),
            ];
        }

        $upserted = $this->upsertMetrics('analytics_pages', $insert, ['date', 'page_path'], ['page_title', 'views', 'users', 'events', 'avg_time_seconds', 'bounce_rate']);

        return SyncReport::of('pages', count($insert), $upserted, $ctx->untilOrNow()->toDateString());
    }

    protected function syncCities(SyncContext $ctx): SyncReport
    {
        $rows = $this->report($ctx, ['date', 'city', 'region', 'country'],
            ['sessions', 'totalUsers', 'transactions', 'purchaseRevenue'], 500);

        $insert = [];
        foreach ($rows as $row) {
            [$date, $city, $region, $country] = $row['dimensions'];
            $m = $row['metrics'];
            $insert[] = [
                'tenant_id' => $ctx->tenant->id,
                'date' => $this->date($date),
                'city' => $city ?: null,
                'region' => $region ?: null,
                'country' => $country ?: null,
                'sessions' => (int) $m[0],
                'users' => (int) $m[1],
                'purchases' => (int) $m[2],
                'revenue' => (int) round(((float) $m[3]) * 100),
            ];
        }

        $upserted = $this->upsertMetrics('analytics_cities', $insert, ['date', 'city', 'region', 'country'], ['sessions', 'users', 'purchases', 'revenue']);

        return SyncReport::of('cities', count($insert), $upserted, $ctx->untilOrNow()->toDateString());
    }

    protected function syncProducts(SyncContext $ctx): SyncReport
    {
        $rows = $this->report($ctx, ['date', 'itemId', 'itemName'],
            ['itemsViewed', 'itemsAddedToCart', 'itemsCheckedOut', 'itemsPurchased', 'itemRevenue'], 500);

        $skus = Sku::query()->where('tenant_id', $ctx->tenant->id)->pluck('id', 'sku_code');

        $insert = [];
        foreach ($rows as $row) {
            [$date, $itemId, $itemName] = $row['dimensions'];
            $m = $row['metrics'];
            $insert[] = [
                'tenant_id' => $ctx->tenant->id,
                'date' => $this->date($date),
                'sku_id' => $skus[$itemId] ?? null,
                'item_id' => $itemId ?: null,
                'item_name' => $itemName ? mb_substr($itemName, 0, 190) : null,
                'views' => (int) $m[0],
                'add_to_carts' => (int) $m[1],
                'checkouts' => (int) $m[2],
                'purchases' => (int) $m[3],
                'revenue' => (int) round(((float) $m[4]) * 100),
            ];
        }

        $upserted = $this->upsertMetrics('analytics_products', $insert, ['date', 'item_id'], ['sku_id', 'item_name', 'views', 'add_to_carts', 'checkouts', 'purchases', 'revenue']);

        return SyncReport::of('products', count($insert), $upserted, $ctx->untilOrNow()->toDateString());
    }

    protected function syncDemographics(SyncContext $ctx): SyncReport
    {
        $rows = $this->report($ctx, ['date', 'userAgeBracket', 'userGender'],
            ['sessions', 'totalUsers', 'transactions', 'purchaseRevenue'], 500);

        if ($rows === []) {
            return SyncReport::failed('demographics',
                'GA4 returned no demographic rows. This normally means Google Signals is off for the property — enable it or the buyer-persona widget stays empty.');
        }

        $insert = [];
        foreach ($rows as $row) {
            [$date, $age, $gender] = $row['dimensions'];
            $m = $row['metrics'];
            $insert[] = [
                'tenant_id' => $ctx->tenant->id,
                'date' => $this->date($date),
                'age' => $age ?: null,
                'gender' => $gender ?: null,
                'sessions' => (int) $m[0],
                'users' => (int) $m[1],
                'purchases' => (int) $m[2],
                'revenue' => (int) round(((float) $m[3]) * 100),
            ];
        }

        $upserted = $this->upsertMetrics('analytics_demographics', $insert, ['date', 'age', 'gender'], ['sessions', 'users', 'purchases', 'revenue']);

        return SyncReport::of('demographics', count($insert), $upserted, $ctx->untilOrNow()->toDateString());
    }

    protected function syncRealtime(SyncContext $ctx): SyncReport
    {
        $response = $this->client()->post($this->property().':runRealtimeReport', [
            'dimensions' => [['name' => 'country'], ['name' => 'unifiedScreenName']],
            'metrics' => [['name' => 'activeUsers']],
            'limit' => 50,
        ]);

        if ($response->failed()) {
            return SyncReport::failed('realtime', 'GA4 realtime returned HTTP '.$response->status().'.');
        }

        $byCountry = [];
        $byPage = [];
        $total = 0;

        foreach ($response->json('rows', []) as $row) {
            $country = $row['dimensionValues'][0]['value'] ?? 'Unknown';
            $page = $row['dimensionValues'][1]['value'] ?? '/';
            $users = (int) ($row['metricValues'][0]['value'] ?? 0);

            $byCountry[$country] = ($byCountry[$country] ?? 0) + $users;
            $byPage[$page] = ($byPage[$page] ?? 0) + $users;
            $total += $users;
        }

        arsort($byCountry);
        arsort($byPage);

        AnalyticsRealtime::query()->create([
            'tenant_id' => $ctx->tenant->id,
            'captured_at' => now(),
            'active_users' => $total,
            'by_country' => $byCountry,
            'by_page' => array_slice($byPage, 0, 10, true),
        ]);

        return SyncReport::of('realtime', 1, 1, now()->toIso8601String());
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<string>  $metrics
     * @return list<array{dimensions: list<string>, metrics: list<string>}>
     */
    private function report(SyncContext $ctx, array $dimensions, array $metrics, int $limit = 100_000): array
    {
        $response = $this->client()->post($this->property().':runReport', [
            'dateRanges' => [[
                'startDate' => $ctx->sinceOrDefault(90)->toDateString(),
                'endDate' => $ctx->untilOrNow()->toDateString(),
            ]],
            'dimensions' => array_map(static fn (string $d): array => ['name' => $d], $dimensions),
            'metrics' => array_map(static fn (string $m): array => ['name' => $m], $metrics),
            'limit' => $limit,
        ]);

        if ($response->failed()) {
            return [];
        }

        return collect($response->json('rows', []))->map(static fn (array $row): array => [
            'dimensions' => array_column($row['dimensionValues'] ?? [], 'value'),
            'metrics' => array_column($row['metricValues'] ?? [], 'value'),
        ])->all();
    }

    private function date(string $ga4Date): string
    {
        return substr($ga4Date, 0, 4).'-'.substr($ga4Date, 4, 2).'-'.substr($ga4Date, 6, 2);
    }

    private function property(): string
    {
        return 'properties/'.preg_replace('/\D/', '', (string) $this->credential('property_id'));
    }

    private function client(): PendingRequest
    {
        $token = $this->oauth->accessToken(
            (string) $this->credential('client_id'),
            (string) $this->credential('client_secret'),
            (string) $this->credential('refresh_token'),
        );

        return $this->http('https://analyticsdata.googleapis.com/v1beta/', [
            'Authorization' => 'Bearer '.$token,
        ]);
    }
}
