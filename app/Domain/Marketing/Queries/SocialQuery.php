<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Queries;

use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Metric;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Instagram and Facebook Page performance.
 *
 * Saves are treated as the buy-intent signal rather than likes — a save is
 * someone coming back to purchase, a like is not.
 */
class SocialQuery
{
    public function caveat(): ?Caveat
    {
        if (DB::table('social_accounts')->where('tenant_id', Tenant::id())->exists()) {
            return null;
        }

        return Caveat::missingConnector('meta', 'Instagram and Facebook performance');
    }

    /** @return list<array<string, mixed>> */
    public function kpis(WidgetFilters $filters): array
    {
        $now = $this->accountTotals($filters, 'instagram');
        $prev = $this->accountTotals($filters->previous(), 'instagram');
        $content = $this->contentTotals($filters);
        $contentPrev = $this->contentTotals($filters->previous());

        $facebook = DB::table('social_accounts')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'facebook')
            ->value('followers_count');

        $series = $this->dailySeries($filters);

        return [
            (new Metric('followers', 'Followers', (float) $now['followers'], (float) $prev['followers'], 'number', true,
                'Follower count at the end of the period.',
                $this->spark($series, 'followers')))->toArray()
                + ['badge' => sprintf('%+d new', $now['new_followers'])],

            (new Metric('reach', 'Reach', (float) $now['reach'], (float) $prev['reach'], 'number', true,
                'Unique accounts that saw your content.',
                $this->spark($series, 'reach')))->toArray(),

            (new Metric('profile_views', 'Profile Visits', (float) $now['profile_views'], (float) $prev['profile_views'], 'number', true,
                null, $this->spark($series, 'profile_views')))->toArray(),

            (new Metric('engagement_rate', 'Engagement Rate',
                $content['engagement_rate'], $contentPrev['engagement_rate'], 'percent', true,
                'Likes, comments, saves and shares as a share of reach, across everything published in the period.'))->toArray(),

            (new Metric('published', 'Content Published',
                (float) $content['posts'], (float) $contentPrev['posts'], 'number', true))->toArray(),

            (new Metric('avg_watch', 'Avg Reel Watch',
                $content['avg_watch_time'], $contentPrev['avg_watch_time'], 'seconds', true,
                'Average seconds watched per reel.'))->toArray(),

            (new Metric('fb_followers', 'FB Followers', (float) ($facebook ?? 0), null, 'number', true))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function accountTrend(WidgetFilters $filters): array
    {
        return ['series' => $this->dailySeries($filters)];
    }

    /** @return array<string, mixed> */
    public function engagementTrend(WidgetFilters $filters): array
    {
        $rows = DB::table('social_posts')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'instagram')
            ->whereBetween('published_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('DATE(published_at) AS d')
            ->selectRaw('COALESCE(SUM(likes),0) AS likes, COALESCE(SUM(comments),0) AS comments')
            ->selectRaw('COALESCE(SUM(shares),0) AS shares, COALESCE(SUM(saves),0) AS saves')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        return [
            'series' => $filters->period->dateKeys()->map(static fn (string $date): array => [
                'date' => $date,
                'likes' => (int) ($rows[$date]->likes ?? 0),
                'comments' => (int) ($rows[$date]->comments ?? 0),
                'shares' => (int) ($rows[$date]->shares ?? 0),
                'saves' => (int) ($rows[$date]->saves ?? 0),
            ])->all(),
            'caveat' => 'Saves are the strongest buy-intent signal here — someone saving a post is planning to come back to it.',
        ];
    }

    /** @return array<string, mixed> */
    public function content(WidgetFilters $filters, string $sort = 'reach', int $limit = 40): array
    {
        $sortable = ['reach' => 'reach', 'recent' => 'published_at', 'engagement' => 'engagement_rate', 'saves' => 'saves'];

        $rows = DB::table('social_posts')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'instagram')
            ->whereIn('type', ['post', 'reel', 'carousel'])
            ->whereBetween('published_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->orderByDesc($sortable[$sort] ?? 'reach')
            ->limit($limit)
            ->get()
            ->map(static fn (object $r): array => [
                'media_id' => $r->media_id,
                'type' => $r->type,
                'caption' => $r->caption,
                'permalink' => $r->permalink,
                'thumbnail_url' => $r->thumbnail_url,
                'published_at' => $r->published_at,
                'reach' => (int) $r->reach,
                'views' => (int) $r->views,
                'likes' => (int) $r->likes,
                'comments' => (int) $r->comments,
                'shares' => (int) $r->shares,
                'saves' => (int) $r->saves,
                'engagement_rate' => (float) $r->engagement_rate,
                'avg_watch_time' => (float) $r->avg_watch_time,
            ]);

        return ['rows' => $rows->all(), 'sort' => $sort];
    }

    /**
     * Reels vs feed — which format actually earns reach and saves.
     *
     * @return array<string, mixed>
     */
    public function reelsVsFeed(WidgetFilters $filters): array
    {
        $rows = DB::table('social_posts')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'instagram')
            ->whereIn('type', ['post', 'reel', 'carousel'])
            ->whereBetween('published_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('type, COUNT(*) AS posts, AVG(reach) AS avg_reach, AVG(engagement_rate) AS avg_engagement')
            ->selectRaw('COALESCE(SUM(saves),0) AS saves, COALESCE(SUM(reach),0) AS total_reach')
            ->groupBy('type')
            ->get()
            ->map(static fn (object $r): array => [
                'type' => $r->type,
                'posts' => (int) $r->posts,
                'avg_reach' => (int) round((float) $r->avg_reach),
                'avg_engagement' => round((float) $r->avg_engagement, 2),
                'saves' => (int) $r->saves,
                'total_reach' => (int) $r->total_reach,
            ]);

        $best = $rows->sortByDesc('avg_reach')->first();
        $worst = $rows->sortBy('avg_reach')->first();

        return [
            'rows' => $rows->sortByDesc('avg_reach')->values()->all(),
            'verdict' => ($rows->count() < 2
                ? Verdict::neutral('Not enough format variety to compare yet.')
                : Verdict::neutral(
                    sprintf('%s earns %.1fx the reach of %s.', ucfirst((string) $best['type']),
                        Num::safeDivide($best['avg_reach'], max($worst['avg_reach'], 1)), $worst['type']),
                    sprintf('%s averages %s reach at %.2f%% engagement.', ucfirst((string) $best['type']),
                        number_format($best['avg_reach']), $best['avg_engagement']),
                    sprintf('Shift production time towards %s.', $best['type']),
                ))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function stories(WidgetFilters $filters): array
    {
        $rows = DB::table('social_posts')
            ->where('tenant_id', Tenant::id())
            ->where('type', 'story')
            ->whereBetween('published_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->orderByDesc('published_at')
            ->limit(50)
            ->get()
            ->map(static fn (object $r): array => [
                'media_id' => $r->media_id,
                'published_at' => $r->published_at,
                'reach' => (int) $r->reach,
                'views' => (int) $r->views,
                'replies' => (int) $r->replies,
            ]);

        return [
            'rows' => $rows->all(),
            'caveat' => $rows->isEmpty()
                ? 'No stories in this window. Instagram only exposes story insights for 24 hours, so anything older than the last sync is gone for good.'
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function audience(): array
    {
        $rows = DB::table('social_audience_daily as a')
            ->join('social_accounts as s', 's.id', '=', 'a.social_account_id')
            ->where('a.tenant_id', Tenant::id())
            ->where('s.platform', 'instagram')
            ->whereIn('a.date', function ($query): void {
                $query->from('social_audience_daily')->selectRaw('MAX(date)')->where('tenant_id', Tenant::id());
            })
            ->selectRaw('a.dimension, a.bucket, a.value')
            ->orderByDesc('a.value')
            ->get();

        return [
            'cities' => $this->bucket($rows, 'city'),
            'countries' => $this->bucket($rows, 'country'),
            'gender_age' => $this->bucket($rows, 'gender_age'),
            'caveat' => $rows->isEmpty()
                ? 'Instagram only returns audience demographics once an account passes 100 followers.'
                : null,
        ];
    }

    /**
     * Best time to post — reach by weekday and hour, from what actually
     * performed rather than a generic industry chart.
     *
     * @return array<string, mixed>
     */
    public function bestTimeToPost(WidgetFilters $filters): array
    {
        $rows = DB::table('social_posts')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'instagram')
            ->whereIn('type', ['post', 'reel', 'carousel'])
            ->selectRaw('DAYOFWEEK(published_at) AS dow, HOUR(published_at) AS hour')
            ->selectRaw('COUNT(*) AS posts, AVG(reach) AS avg_reach')
            ->groupBy('dow', 'hour')
            ->get();

        $days = ['', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $cells = $rows->map(static fn (object $r): array => [
            'day' => $days[(int) $r->dow] ?? '',
            'day_index' => (int) $r->dow,
            'hour' => (int) $r->hour,
            'posts' => (int) $r->posts,
            'avg_reach' => (int) round((float) $r->avg_reach),
        ]);

        $best = $cells->sortByDesc('avg_reach')->first();

        return [
            'cells' => $cells->values()->all(),
            'max_reach' => (int) $cells->max('avg_reach'),
            'caveat' => 'Based only on when you have actually posted — slots you have never tried cannot be ranked.',
            'verdict' => ($best === null
                ? Verdict::neutral('Not enough posts to find a pattern.')
                : Verdict::neutral(
                    sprintf('%s around %02d:00 has earned the most reach.', $best['day'], $best['hour']),
                    sprintf('%s average reach across %d post%s.', number_format($best['avg_reach']), $best['posts'], $best['posts'] === 1 ? '' : 's'),
                ))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function hashtags(WidgetFilters $filters, int $limit = 25): array
    {
        $posts = DB::table('social_posts')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'instagram')
            ->whereBetween('published_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->whereNotNull('hashtags')
            ->get(['hashtags', 'reach', 'saves', 'engagement_rate']);

        $tally = [];

        foreach ($posts as $post) {
            foreach (json_decode((string) $post->hashtags, true) ?: [] as $tag) {
                $tally[$tag] ??= ['tag' => $tag, 'posts' => 0, 'reach' => 0, 'saves' => 0, 'engagement' => 0.0];
                $tally[$tag]['posts']++;
                $tally[$tag]['reach'] += (int) $post->reach;
                $tally[$tag]['saves'] += (int) $post->saves;
                $tally[$tag]['engagement'] += (float) $post->engagement_rate;
            }
        }

        $rows = collect($tally)
            ->map(static fn (array $row): array => [
                ...$row,
                'avg_reach' => (int) round(Num::safeDivide($row['reach'], $row['posts'])),
                'avg_engagement' => round(Num::safeDivide($row['engagement'], $row['posts']), 2),
            ])
            ->sortByDesc('avg_reach')
            ->take($limit)
            ->values();

        return [
            'rows' => $rows->all(),
            'caveat' => 'Reach is attributed to every hashtag on a post, so a tag that always appears alongside a strong one will look strong too.',
        ];
    }

    /**
     * Organic reach against net sales. Deliberately not called attribution —
     * it is a visual correlation, and the caveat says so.
     *
     * @return array<string, mixed>
     */
    public function salesCorrelation(WidgetFilters $filters): array
    {
        $reach = DB::table('social_account_daily')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'instagram')
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('date, COALESCE(SUM(reach),0) AS reach')
            ->groupBy('date')
            ->pluck('reach', 'date');

        $sales = DB::table('daily_metrics_rollup')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('date, COALESCE(SUM(net_sales),0) AS net_sales')
            ->groupBy('date')
            ->pluck('net_sales', 'date');

        $series = $filters->period->dateKeys()->map(static fn (string $date): array => [
            'date' => $date,
            'reach' => (int) ($reach[$date] ?? 0),
            'net_sales' => (int) ($sales[$date] ?? 0),
        ]);

        return [
            'series' => $series->all(),
            'caveat' => 'This is a visual comparison, not attribution. Organic reach and sales often move together because both follow the same promotions — neither proves it caused the other.',
        ];
    }

    /** @return array<string, mixed> */
    public function facebookPage(WidgetFilters $filters): array
    {
        $rows = DB::table('social_account_daily')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'facebook')
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('date, COALESCE(SUM(reach),0) AS reach, COALESCE(SUM(profile_views),0) AS views, MAX(followers) AS followers')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(static fn (object $r): array => [
                'date' => (string) $r->date,
                'reach' => (int) $r->reach,
                'views' => (int) $r->views,
                'followers' => (int) $r->followers,
            ]);

        return [
            'series' => $rows->all(),
            'caveat' => $rows->isEmpty() ? 'No Facebook Page connected, or the page has no insights in this window.' : null,
        ];
    }

    /** @param Collection<int, object> $rows */
    private function bucket(Collection $rows, string $dimension): array
    {
        return $rows->where('dimension', $dimension)
            ->map(static fn (object $r): array => ['label' => $r->bucket, 'value' => (int) $r->value])
            ->values()
            ->all();
    }

    /** @return list<array{date: string, followers: int, reach: int, profile_views: int, new_followers: int}> */
    private function dailySeries(WidgetFilters $filters): array
    {
        $rows = DB::table('social_account_daily')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'instagram')
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('date, MAX(followers) AS followers, COALESCE(SUM(reach),0) AS reach')
            ->selectRaw('COALESCE(SUM(profile_views),0) AS profile_views, COALESCE(SUM(new_followers),0) AS new_followers')
            ->selectRaw('COALESCE(SUM(views),0) AS views')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        return $filters->period->dateKeys()->map(static fn (string $date): array => [
            'date' => $date,
            'followers' => (int) ($rows[$date]->followers ?? 0),
            'reach' => (int) ($rows[$date]->reach ?? 0),
            'views' => (int) ($rows[$date]->views ?? 0),
            'profile_views' => (int) ($rows[$date]->profile_views ?? 0),
            'new_followers' => (int) ($rows[$date]->new_followers ?? 0),
        ])->all();
    }

    /** @return array<string, int> */
    private function accountTotals(WidgetFilters $filters, string $platform): array
    {
        $row = DB::table('social_account_daily')
            ->where('tenant_id', Tenant::id())
            ->where('platform', $platform)
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('COALESCE(SUM(reach),0) AS reach, COALESCE(SUM(profile_views),0) AS profile_views')
            ->selectRaw('COALESCE(SUM(new_followers),0) AS new_followers')
            ->first();

        $followers = (int) DB::table('social_account_daily')
            ->where('tenant_id', Tenant::id())
            ->where('platform', $platform)
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->orderByDesc('date')
            ->value('followers');

        return [
            'followers' => $followers,
            'reach' => (int) ($row->reach ?? 0),
            'profile_views' => (int) ($row->profile_views ?? 0),
            'new_followers' => (int) ($row->new_followers ?? 0),
        ];
    }

    /** @return array<string, float|int> */
    private function contentTotals(WidgetFilters $filters): array
    {
        $row = DB::table('social_posts')
            ->where('tenant_id', Tenant::id())
            ->where('platform', 'instagram')
            ->whereIn('type', ['post', 'reel', 'carousel'])
            ->whereBetween('published_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('COUNT(*) AS posts, COALESCE(SUM(reach),0) AS reach')
            ->selectRaw('COALESCE(SUM(likes + comments + shares + saves),0) AS interactions')
            ->selectRaw('AVG(CASE WHEN type = ? THEN avg_watch_time END) AS avg_watch', ['reel'])
            ->first();

        return [
            'posts' => (int) ($row->posts ?? 0),
            'engagement_rate' => Num::pct((int) ($row->interactions ?? 0), (int) ($row->reach ?? 0)),
            'avg_watch_time' => round((float) ($row->avg_watch ?? 0), 1),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $series
     * @return list<array{date: string, value: float}>
     */
    private function spark(array $series, string $key): array
    {
        return array_map(static fn (array $row): array => [
            'date' => (string) $row['date'],
            'value' => (float) $row[$key],
        ], $series);
    }
}
