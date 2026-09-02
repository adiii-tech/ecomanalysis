<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers;

use App\Domain\Connectors\Contracts\SupportsOAuth;
use App\Domain\Connectors\DTOs\ConnectionResult;
use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Domain\Connectors\Support\AbstractConnector;
use App\Enums\AuthType;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\SocialAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Meta Marketing API + Instagram Graph API.
 */
class MetaConnector extends AbstractConnector implements SupportsOAuth
{
    private const API_VERSION = 'v21.0';

    public function id(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta (Facebook & Instagram)';
    }

    public function summary(): string
    {
        return 'Ad accounts, campaigns, ad sets, ads, daily insights with placement and demographic breakdowns, plus Instagram media insights and Facebook Page reach.';
    }

    public function authType(): AuthType
    {
        return AuthType::OAuth;
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function credentialFields(): array
    {
        return [
            'access_token' => ['label' => 'Long-lived access token', 'type' => 'password', 'required' => true],
            'ad_account_id' => ['label' => 'Ad account ID', 'type' => 'text', 'required' => true, 'help' => 'act_1234567890'],
            'ig_user_id' => ['label' => 'Instagram business account ID', 'type' => 'text', 'required' => false],
            'page_id' => ['label' => 'Facebook Page ID', 'type' => 'text', 'required' => false],
        ];
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['ad_accounts', 'campaigns', 'ad_sets', 'ads', 'insights', 'breakdowns', 'instagram_media', 'instagram_audience', 'page_insights'];
    }

    /** @return array<string, int> */
    public function syncCadence(): array
    {
        return [
            'ad_accounts' => 1440, 'campaigns' => 180, 'ad_sets' => 180, 'ads' => 180,
            'insights' => 60, 'breakdowns' => 360, 'instagram_media' => 180,
            'instagram_audience' => 1440, 'page_insights' => 360,
        ];
    }

    /** @return list<string> */
    public function oauthScopes(): array
    {
        return [
            'ads_read',
            'business_management',
            'instagram_basic',
            'instagram_manage_insights',
            'pages_read_engagement',
            'pages_show_list',
            'read_insights',
        ];
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function preAuthFields(): array
    {
        return [];
    }

    /** @param array<string, mixed> $context */
    public function authorizationUrl(string $state, string $redirectUri, array $context = []): string
    {
        return 'https://www.facebook.com/'.self::API_VERSION.'/dialog/oauth?'.http_build_query([
            'client_id' => (string) config('services.meta.client_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', $this->oauthScopes()),
            'response_type' => 'code',
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $context
     */
    public function exchangeCode(array $query, string $redirectUri, array $context = []): ConnectionResult
    {
        $short = Http::timeout(20)->get('https://graph.facebook.com/'.self::API_VERSION.'/oauth/access_token', [
            'client_id' => (string) config('services.meta.client_id'),
            'client_secret' => (string) config('services.meta.client_secret'),
            'redirect_uri' => $redirectUri,
            'code' => (string) ($query['code'] ?? ''),
        ]);

        if ($short->failed()) {
            return ConnectionResult::failure('Meta refused the authorisation code: '.($short->json('error.message') ?? 'unknown error'));
        }

        // The code exchange returns a token valid for about an hour. Trading it
        // for a long-lived one buys ~60 days before the merchant must re-auth.
        $long = Http::timeout(20)->get('https://graph.facebook.com/'.self::API_VERSION.'/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => (string) config('services.meta.client_id'),
            'client_secret' => (string) config('services.meta.client_secret'),
            'fb_exchange_token' => (string) $short->json('access_token'),
        ]);

        $token = (string) ($long->json('access_token') ?? $short->json('access_token'));
        $expiresIn = (int) ($long->json('expires_in') ?? 0);

        $profile = Http::timeout(20)->get('https://graph.facebook.com/'.self::API_VERSION.'/me', [
            'access_token' => $token,
            'fields' => 'name',
        ]);

        return ConnectionResult::success((string) ($profile->json('name') ?? 'Meta'), [
            'access_token' => $token,
            'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn)->toIso8601String() : null,
        ]);
    }

    /**
     * A Meta token can see many ad accounts and pages — the merchant has to say
     * which one this tenant is, or every number would be wrong.
     *
     * @return array<string, string>
     */
    public function pendingSelections(): array
    {
        return [
            'ad_account_id' => 'Ad account',
            'ig_user_id' => 'Instagram business account',
            'page_id' => 'Facebook Page',
        ];
    }

    /** @return list<array{id: string, label: string, meta?: string}> */
    public function availableResources(string $key): array
    {
        $token = (string) $this->credential('access_token');
        $client = $this->http('https://graph.facebook.com/'.self::API_VERSION.'/')
            ->withQueryParameters(['access_token' => $token]);

        return match ($key) {
            'ad_account_id' => collect($client->get('me/adaccounts', ['fields' => 'name,account_id,currency,account_status', 'limit' => 100])->json('data', []))
                ->map(static fn (array $row): array => [
                    'id' => (string) $row['id'],
                    'label' => (string) ($row['name'] ?? $row['id']),
                    'meta' => trim(($row['currency'] ?? '').((int) ($row['account_status'] ?? 1) === 1 ? '' : ' · inactive')),
                ])->values()->all(),

            'ig_user_id' => collect($client->get('me/accounts', ['fields' => 'name,instagram_business_account{id,username}', 'limit' => 100])->json('data', []))
                ->filter(static fn (array $row): bool => isset($row['instagram_business_account']['id']))
                ->map(static fn (array $row): array => [
                    'id' => (string) $row['instagram_business_account']['id'],
                    'label' => '@'.($row['instagram_business_account']['username'] ?? 'instagram'),
                    'meta' => (string) ($row['name'] ?? ''),
                ])->values()->all(),

            'page_id' => collect($client->get('me/accounts', ['fields' => 'name,category', 'limit' => 100])->json('data', []))
                ->map(static fn (array $row): array => [
                    'id' => (string) $row['id'],
                    'label' => (string) ($row['name'] ?? $row['id']),
                    'meta' => (string) ($row['category'] ?? ''),
                ])->values()->all(),

            default => [],
        };
    }

    public function afterConnect(): void
    {
        //
    }

    public function testConnection(): HealthResult
    {
        try {
            $response = $this->client()->get($this->accountId(), ['fields' => 'name,currency,account_status']);
        } catch (Throwable $e) {
            return HealthResult::fail('Meta unreachable: '.$e->getMessage());
        }

        if ($response->failed()) {
            return HealthResult::fail('Meta returned: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }

        return HealthResult::ok('Connected to '.$response->json('name').'.', [
            'currency' => $response->json('currency'),
            'account_status' => $response->json('account_status'),
        ]);
    }

    protected function syncAdAccounts(SyncContext $ctx): SyncReport
    {
        $response = $this->client()->get($this->accountId(), ['fields' => 'name,currency,account_status']);

        if ($response->failed()) {
            return SyncReport::failed('ad_accounts', $response->json('error.message') ?? 'Meta request failed.');
        }

        AdAccount::query()->updateOrCreate(
            ['tenant_id' => $ctx->tenant->id, 'platform' => 'meta', 'external_id' => $this->accountId()],
            [
                'name' => $response->json('name', 'Meta Ads'),
                'currency' => $response->json('currency', 'INR'),
                'status' => (int) $response->json('account_status', 1) === 1 ? 'active' : 'inactive',
            ],
        );

        return SyncReport::of('ad_accounts', 1, 1, now()->toIso8601String());
    }

    protected function syncCampaigns(SyncContext $ctx): SyncReport
    {
        $account = $this->accountModel($ctx);
        $fetched = 0;

        foreach ($this->pages($this->accountId().'/campaigns', ['fields' => 'name,objective,status,daily_budget,lifetime_budget,start_time,stop_time']) as $campaign) {
            $fetched++;
            Campaign::query()->updateOrCreate(
                ['tenant_id' => $ctx->tenant->id, 'platform' => 'meta', 'external_id' => (string) $campaign['id']],
                [
                    'ad_account_id' => $account?->id,
                    'name' => $campaign['name'],
                    'objective' => $campaign['objective'] ?? null,
                    'status' => strtolower((string) ($campaign['status'] ?? 'active')),
                    'daily_budget' => (int) ($campaign['daily_budget'] ?? 0),
                    'lifetime_budget' => (int) ($campaign['lifetime_budget'] ?? 0),
                    'started_at' => $campaign['start_time'] ?? null,
                    'stopped_at' => $campaign['stop_time'] ?? null,
                ],
            );
        }

        return SyncReport::of('campaigns', $fetched, $fetched, now()->toIso8601String());
    }

    protected function syncAdSets(SyncContext $ctx): SyncReport
    {
        $campaigns = Campaign::query()->where('tenant_id', $ctx->tenant->id)->where('platform', 'meta')->pluck('id', 'external_id');
        $fetched = 0;

        foreach ($this->pages($this->accountId().'/adsets', ['fields' => 'name,status,daily_budget,campaign_id,targeting']) as $adSet) {
            $campaignId = $campaigns[(string) ($adSet['campaign_id'] ?? '')] ?? null;
            if ($campaignId === null) {
                continue;
            }

            $fetched++;
            AdSet::query()->updateOrCreate(
                ['tenant_id' => $ctx->tenant->id, 'platform' => 'meta', 'external_id' => (string) $adSet['id']],
                [
                    'campaign_id' => $campaignId,
                    'name' => $adSet['name'],
                    'status' => strtolower((string) ($adSet['status'] ?? 'active')),
                    'daily_budget' => (int) ($adSet['daily_budget'] ?? 0),
                    'targeting' => $adSet['targeting'] ?? null,
                ],
            );
        }

        return SyncReport::of('ad_sets', $fetched, $fetched, now()->toIso8601String());
    }

    protected function syncAds(SyncContext $ctx): SyncReport
    {
        $adSets = AdSet::query()->where('tenant_id', $ctx->tenant->id)->where('platform', 'meta')->pluck('id', 'external_id');
        $campaigns = Campaign::query()->where('tenant_id', $ctx->tenant->id)->where('platform', 'meta')->pluck('id', 'external_id');
        $fetched = 0;
        $creatives = [];

        foreach ($this->pages($this->accountId().'/ads', ['fields' => 'name,status,adset_id,campaign_id,creative{id,name,thumbnail_url,body,title,call_to_action_type,object_type}']) as $ad) {
            $fetched++;
            $model = Ad::query()->updateOrCreate(
                ['tenant_id' => $ctx->tenant->id, 'platform' => 'meta', 'external_id' => (string) $ad['id']],
                [
                    'ad_set_id' => $adSets[(string) ($ad['adset_id'] ?? '')] ?? null,
                    'campaign_id' => $campaigns[(string) ($ad['campaign_id'] ?? '')] ?? null,
                    'name' => $ad['name'],
                    'status' => strtolower((string) ($ad['status'] ?? 'active')),
                ],
            );

            if (isset($ad['creative']['id'])) {
                $creatives[] = [
                    'tenant_id' => $ctx->tenant->id,
                    'ad_id' => $model->id,
                    'platform' => 'meta',
                    'external_id' => (string) $ad['creative']['id'],
                    'name' => $ad['creative']['name'] ?? null,
                    'format' => $ad['creative']['object_type'] ?? null,
                    'thumbnail_url' => $ad['creative']['thumbnail_url'] ?? null,
                    'body' => $ad['creative']['body'] ?? null,
                    'title' => $ad['creative']['title'] ?? null,
                    'call_to_action' => $ad['creative']['call_to_action_type'] ?? null,
                    'first_seen_at' => now(),
                ];
            }
        }

        $this->upsert('ad_creatives', $creatives, ['tenant_id', 'platform', 'external_id'],
            ['ad_id', 'name', 'format', 'thumbnail_url', 'body', 'title', 'call_to_action', 'updated_at']);

        return SyncReport::of('ads', $fetched, $fetched, now()->toIso8601String());
    }

    protected function syncInsights(SyncContext $ctx): SyncReport
    {
        return $this->pullInsights($ctx, 'insights', 'total', []);
    }

    protected function syncBreakdowns(SyncContext $ctx): SyncReport
    {
        $report = SyncReport::empty('breakdowns');

        foreach ([
            'placement' => ['publisher_platform', 'platform_position'],
            'demographic' => ['age', 'gender'],
            'geo' => ['region'],
        ] as $key => $breakdowns) {
            $report = $report->merge($this->pullInsights($ctx, 'breakdowns', $key, $breakdowns));
        }

        return $report;
    }

    /**
     * @param  list<string>  $breakdowns
     */
    private function pullInsights(SyncContext $ctx, string $entity, string $breakdownKey, array $breakdowns): SyncReport
    {
        $since = $ctx->cursor !== null ? Carbon::parse((string) $ctx->cursor)->subDays(3) : $ctx->sinceOrDefault(90);
        $until = $ctx->untilOrNow();

        $campaigns = Campaign::query()->where('tenant_id', $ctx->tenant->id)->where('platform', 'meta')->pluck('id', 'external_id');
        $adSets = AdSet::query()->where('tenant_id', $ctx->tenant->id)->where('platform', 'meta')->pluck('id', 'external_id');
        $ads = Ad::query()->where('tenant_id', $ctx->tenant->id)->where('platform', 'meta')->pluck('id', 'external_id');

        $params = [
            'level' => 'ad',
            'time_increment' => 1,
            'time_range' => json_encode(['since' => $since->toDateString(), 'until' => $until->toDateString()]),
            'fields' => 'campaign_id,adset_id,ad_id,spend,impressions,clicks,reach,actions,action_values',
            'limit' => 500,
        ];

        if ($breakdowns !== []) {
            $params['breakdowns'] = implode(',', $breakdowns);
        }

        $rows = [];
        $fetched = 0;

        foreach ($this->pages($this->accountId().'/insights', $params) as $insight) {
            $fetched++;
            $rows[] = [
                'tenant_id' => $ctx->tenant->id,
                'date' => $insight['date_start'],
                'platform' => 'meta',
                'campaign_id' => $campaigns[(string) ($insight['campaign_id'] ?? '')] ?? null,
                'ad_set_id' => $adSets[(string) ($insight['adset_id'] ?? '')] ?? null,
                'ad_id' => $ads[(string) ($insight['ad_id'] ?? '')] ?? null,
                'breakdown_key' => $breakdownKey,
                'placement' => isset($insight['publisher_platform'])
                    ? trim(($insight['publisher_platform'] ?? '').' '.($insight['platform_position'] ?? ''))
                    : null,
                'age' => $insight['age'] ?? null,
                'gender' => $insight['gender'] ?? null,
                'country' => $insight['country'] ?? null,
                'state' => $insight['region'] ?? null,
                'spend' => (int) round(((float) ($insight['spend'] ?? 0)) * 100),
                'impressions' => (int) ($insight['impressions'] ?? 0),
                'clicks' => (int) ($insight['clicks'] ?? 0),
                'reach' => (int) ($insight['reach'] ?? 0),
                'conversions' => $this->actionValue($insight['actions'] ?? [], 'purchase'),
                'conversion_value' => (int) round($this->actionValue($insight['action_values'] ?? [], 'purchase', true) * 100),
                'add_to_carts' => $this->actionValue($insight['actions'] ?? [], 'add_to_cart'),
                'checkouts' => $this->actionValue($insight['actions'] ?? [], 'initiate_checkout'),
                'video_views_3s' => $this->actionValue($insight['actions'] ?? [], 'video_view'),
            ];
        }

        $upserted = $this->upsertMetrics('ad_insights_daily', $rows, ['date', 'platform', 'breakdown_key', 'campaign_id', 'ad_set_id', 'ad_id', 'placement', 'age', 'gender', 'country', 'state'], ['spend', 'impressions', 'clicks', 'reach', 'conversions', 'conversion_value', 'add_to_carts', 'checkouts', 'video_views_3s', 'video_views_thruplay']);

        return SyncReport::of($entity, $fetched, $upserted, $until->toIso8601String());
    }

    protected function syncInstagramMedia(SyncContext $ctx): SyncReport
    {
        $igUserId = $this->credential('ig_user_id');

        if (blank($igUserId)) {
            return SyncReport::failed('instagram_media', 'No Instagram business account ID configured — Instagram widgets stay empty rather than showing invented numbers.');
        }

        $profile = $this->client()->get((string) $igUserId, [
            'fields' => 'username,name,profile_picture_url,followers_count,follows_count,media_count',
        ])->json();

        $account = SocialAccount::query()->updateOrCreate(
            ['tenant_id' => $ctx->tenant->id, 'platform' => 'instagram', 'external_id' => (string) $igUserId],
            [
                'username' => $profile['username'] ?? null,
                'name' => $profile['name'] ?? null,
                'profile_picture_url' => $profile['profile_picture_url'] ?? null,
                'followers_count' => (int) ($profile['followers_count'] ?? 0),
                'follows_count' => (int) ($profile['follows_count'] ?? 0),
                'media_count' => (int) ($profile['media_count'] ?? 0),
            ],
        );

        $rows = [];
        $fetched = 0;

        foreach ($this->pages($igUserId.'/media', [
            'fields' => 'id,caption,media_type,media_product_type,permalink,thumbnail_url,timestamp,like_count,comments_count,insights.metric(reach,saved,shares,total_interactions,views)',
            'limit' => 100,
        ]) as $media) {
            $fetched++;
            $insights = collect($media['insights']['data'] ?? [])
                ->mapWithKeys(fn (array $i): array => [$i['name'] => (int) ($i['values'][0]['value'] ?? 0)]);

            $reach = $insights['reach'] ?? 0;
            $likes = (int) ($media['like_count'] ?? 0);
            $comments = (int) ($media['comments_count'] ?? 0);
            $saves = $insights['saved'] ?? 0;
            $shares = $insights['shares'] ?? 0;

            $rows[] = [
                'tenant_id' => $ctx->tenant->id,
                'social_account_id' => $account->id,
                'platform' => 'instagram',
                'media_id' => (string) $media['id'],
                'type' => $this->mapMediaType($media),
                'caption' => $media['caption'] ?? null,
                'permalink' => $media['permalink'] ?? null,
                'thumbnail_url' => $media['thumbnail_url'] ?? null,
                'published_at' => Carbon::parse($media['timestamp']),
                'reach' => $reach,
                'impressions' => $insights['views'] ?? $reach,
                'views' => $insights['views'] ?? 0,
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
                'saves' => $saves,
                'engagement_rate' => $reach > 0 ? round((($likes + $comments + $saves + $shares) / $reach) * 100, 3) : 0,
                'hashtags' => json_encode($this->extractHashtags($media['caption'] ?? '')),
            ];
        }

        $upserted = $this->upsert('social_posts', $rows, ['tenant_id', 'platform', 'media_id'], [
            'reach', 'impressions', 'views', 'likes', 'comments', 'shares', 'saves',
            'engagement_rate', 'caption', 'thumbnail_url', 'type', 'hashtags', 'updated_at',
        ]);

        return SyncReport::of('instagram_media', $fetched, $upserted, now()->toIso8601String());
    }

    protected function syncInstagramAudience(SyncContext $ctx): SyncReport
    {
        $igUserId = $this->credential('ig_user_id');

        if (blank($igUserId)) {
            return SyncReport::failed('instagram_audience', 'No Instagram business account ID configured.');
        }

        $account = SocialAccount::query()
            ->where('tenant_id', $ctx->tenant->id)
            ->where('platform', 'instagram')
            ->firstOrFail();

        $rows = [];
        $fetched = 0;
        $today = now()->toDateString();

        foreach (['audience_city' => 'city', 'audience_country' => 'country', 'audience_gender_age' => 'gender_age'] as $metric => $dimension) {
            $response = $this->client()->get($igUserId.'/insights', [
                'metric' => $metric,
                'period' => 'lifetime',
                'metric_type' => 'total_value',
            ]);

            foreach ($response->json('data.0.total_value.breakdowns.0.results', []) as $result) {
                $fetched++;
                $rows[] = [
                    'tenant_id' => $ctx->tenant->id,
                    'social_account_id' => $account->id,
                    'date' => $today,
                    'dimension' => $dimension,
                    'bucket' => implode(' · ', $result['dimension_values'] ?? []),
                    'value' => (int) ($result['value'] ?? 0),
                ];
            }
        }

        $upserted = $this->upsert(
            'social_audience_daily',
            $rows,
            ['tenant_id', 'social_account_id', 'date', 'dimension', 'bucket'],
            ['value', 'updated_at'],
        );

        return SyncReport::of('instagram_audience', $fetched, $upserted, now()->toIso8601String());
    }

    protected function syncPageInsights(SyncContext $ctx): SyncReport
    {
        $pageId = $this->credential('page_id');

        if (blank($pageId)) {
            return SyncReport::failed('page_insights', 'No Facebook Page ID configured.');
        }

        $page = $this->client()->get((string) $pageId, ['fields' => 'name,fan_count'])->json();

        $account = SocialAccount::query()->updateOrCreate(
            ['tenant_id' => $ctx->tenant->id, 'platform' => 'facebook', 'external_id' => (string) $pageId],
            ['name' => $page['name'] ?? null, 'followers_count' => (int) ($page['fan_count'] ?? 0)],
        );

        $response = $this->client()->get($pageId.'/insights', [
            'metric' => 'page_impressions_unique,page_views_total,page_post_engagements',
            'period' => 'day',
            'since' => $ctx->sinceOrDefault(30)->toDateString(),
            'until' => $ctx->untilOrNow()->toDateString(),
        ]);

        $byDate = [];
        foreach ($response->json('data', []) as $metric) {
            foreach ($metric['values'] ?? [] as $value) {
                $date = Carbon::parse($value['end_time'])->toDateString();
                $byDate[$date][$metric['name']] = (int) ($value['value'] ?? 0);
            }
        }

        $rows = [];
        foreach ($byDate as $date => $metrics) {
            $rows[] = [
                'tenant_id' => $ctx->tenant->id,
                'social_account_id' => $account->id,
                'date' => $date,
                'platform' => 'facebook',
                'followers' => $account->followers_count,
                'reach' => $metrics['page_impressions_unique'] ?? 0,
                'impressions' => $metrics['page_impressions_unique'] ?? 0,
                'profile_views' => $metrics['page_views_total'] ?? 0,
            ];
        }

        $upserted = $this->upsert('social_account_daily', $rows,
            ['tenant_id', 'social_account_id', 'date'],
            ['followers', 'reach', 'impressions', 'profile_views', 'updated_at'],
        );

        return SyncReport::of('page_insights', count($rows), $upserted, now()->toIso8601String());
    }

    /** @param array<string, mixed> $media */
    private function mapMediaType(array $media): string
    {
        return match ($media['media_product_type'] ?? $media['media_type'] ?? '') {
            'REELS' => 'reel',
            'STORY' => 'story',
            'CAROUSEL_ALBUM' => 'carousel',
            default => 'post',
        };
    }

    /** @return list<string> */
    private function extractHashtags(string $caption): array
    {
        preg_match_all('/#(\w+)/u', $caption, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @param list<array{action_type?: string, value?: mixed}> $actions */
    private function actionValue(array $actions, string $type, bool $asFloat = false): int|float
    {
        foreach ($actions as $action) {
            if (str_contains((string) ($action['action_type'] ?? ''), $type)) {
                return $asFloat ? (float) ($action['value'] ?? 0) : (int) ($action['value'] ?? 0);
            }
        }

        return $asFloat ? 0.0 : 0;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return iterable<int, array<string, mixed>>
     */
    private function pages(string $path, array $params): iterable
    {
        $client = $this->client();
        $after = null;
        $guard = 0;

        do {
            $response = $client->get($path, $after === null ? $params : [...$params, 'after' => $after]);

            if ($response->failed()) {
                return;
            }

            yield from $response->json('data', []);

            $after = $response->json('paging.cursors.after');
            $hasNext = $response->json('paging.next') !== null;
            $guard++;
        } while ($hasNext && $after !== null && $guard < 400);
    }

    private function accountId(): string
    {
        $id = (string) $this->credential('ad_account_id');

        return str_starts_with($id, 'act_') ? $id : 'act_'.$id;
    }

    private function accountModel(SyncContext $ctx): ?AdAccount
    {
        return AdAccount::query()
            ->where('tenant_id', $ctx->tenant->id)
            ->where('platform', 'meta')
            ->where('external_id', $this->accountId())
            ->first();
    }

    private function client(): PendingRequest
    {
        return $this->http('https://graph.facebook.com/'.self::API_VERSION.'/')
            ->withQueryParameters(['access_token' => (string) $this->credential('access_token')]);
    }
}
