<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Marketing\Queries\CampaignQuery;
use App\Domain\Marketing\Queries\FunnelQuery;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Metric;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingController extends Controller
{
    use ResolvesFilters;

    public function kpis(Request $request, RollupQuery $rollups): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'kpis', $filters, function () use ($rollups, $filters): array {
            $now = $rollups->totals($filters);
            $prev = $rollups->totals($filters->previous());
            $ads = $rollups->adSpend($filters);
            $adsPrev = $rollups->adSpend($filters->previous());

            $cac = (int) round(Num::safeDivide($ads['total'], $now['orders_count']));
            $cacPrev = (int) round(Num::safeDivide($adsPrev['total'], $prev['orders_count']));
            $newCac = (int) round(Num::safeDivide($ads['total'], $now['new_customers']));
            $newCacPrev = (int) round(Num::safeDivide($adsPrev['total'], $prev['new_customers']));

            return [
                (new Metric('gross_sales', 'Gross Sales', (float) $now['gross_sales'], (float) $prev['gross_sales'], 'currency'))->toArray(),
                (new Metric('ad_spend', 'Total Ad Spend', (float) $ads['total'], (float) $adsPrev['total'], 'currency', false))->toArray(),
                (new Metric('blended_roas', 'Blended ROAS',
                    Num::ratio($now['net_sales'], $ads['total']),
                    Num::ratio($prev['net_sales'], $adsPrev['total']),
                    'ratio', true, 'Total net sales divided by total ad spend — the only ROAS that reconciles to your bank.'))->toArray(),
                (new Metric('attributed_sales', 'Attributed Sales', (float) $ads['conversion_value'], (float) $adsPrev['conversion_value'], 'currency', true,
                    'What the ad platforms claim they generated, inside their own attribution windows.'))->toArray(),
                (new Metric('attributed_roas', 'Attributed ROAS',
                    Num::ratio($ads['conversion_value'], $ads['total']),
                    Num::ratio($adsPrev['conversion_value'], $adsPrev['total']),
                    'ratio'))->toArray(),
                (new Metric('spend_of_revenue', 'Ad Spend % of Revenue',
                    Num::pct($ads['total'], $now['net_sales']),
                    Num::pct($adsPrev['total'], $prev['net_sales']),
                    'percent', false))->toArray(),
                (new Metric('cac', 'CAC', (float) $cac, (float) $cacPrev, 'currency', false,
                    'Total ad spend divided by all orders — blended, not per-channel.'))->toArray(),
                (new Metric('new_customer_cac', 'New-customer CAC', (float) $newCac, (float) $newCacPrev, 'currency', false,
                    'Total ad spend divided by first-time customers only. This is the number that decides whether growth is affordable.'))->toArray(),
            ];
        }), $filters);
    }

    public function campaigns(Request $request, CampaignQuery $campaigns): JsonResponse
    {
        $filters = $this->filters($request);
        $data = $this->cached('marketing', 'campaigns', $filters, fn (): array => $campaigns->table($filters));

        return ApiResponse::ok($data, $filters);
    }

    public function insights(Request $request, CampaignQuery $campaigns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            ['cards' => $this->cached('marketing', 'insight_cards', $filters, fn (): array => $campaigns->insightCards($filters))],
            $filters,
        );
    }

    public function trend(Request $request, CampaignQuery $campaigns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'trend', $filters, fn (): array => $campaigns->trend($filters)), $filters);
    }

    public function attributionGap(Request $request, CampaignQuery $campaigns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'attribution_gap', $filters, fn (): array => $campaigns->attributionGap($filters)), $filters);
    }

    public function conversionFunnel(Request $request, FunnelQuery $funnel): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'funnel', $filters, fn (): array => $funnel->conversionFunnel($filters)), $filters);
    }

    public function channelPerformance(Request $request, FunnelQuery $funnel): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'channel_perf', $filters, fn (): array => $funnel->channelPerformance($filters)), $filters);
    }

    public function abandonedCarts(Request $request, FunnelQuery $funnel): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'abandoned_carts', $filters, fn (): array => $funnel->abandonedCarts($filters)), $filters);
    }

    public function buyerPersona(Request $request, FunnelQuery $funnel): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'buyer_persona', $filters, fn (): array => $funnel->buyerPersona($filters)), $filters);
    }

    /** Realtime is deliberately uncached — it is the one widget where staleness is the bug. */
    public function realtimeActiveUsers(): JsonResponse
    {
        $row = DB::table('analytics_realtime')
            ->where('tenant_id', Tenant::id())
            ->orderByDesc('captured_at')
            ->first();

        if ($row === null) {
            return ApiResponse::ok([
                'active_users' => null,
                'captured_at' => null,
                'caveat' => ['message' => 'GA4 realtime is not syncing yet, so there is no live user count to show.', 'level' => 'warning', 'connector' => 'ga4'],
            ]);
        }

        return ApiResponse::ok([
            'active_users' => (int) $row->active_users,
            'captured_at' => $row->captured_at,
            'by_country' => json_decode((string) $row->by_country, true) ?: [],
            'by_page' => json_decode((string) $row->by_page, true) ?: [],
            'is_stale' => now()->diffInMinutes($row->captured_at) > 10,
        ]);
    }

    public function topPages(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'top_pages', $filters, function () use ($filters): array {
            $rows = DB::table('analytics_pages')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw('page_path, MAX(page_title) AS page_title')
                ->selectRaw('COALESCE(SUM(views),0) AS views, COALESCE(SUM(users),0) AS users, COALESCE(SUM(events),0) AS events')
                ->selectRaw('AVG(avg_time_seconds) AS avg_time, AVG(bounce_rate) AS bounce_rate')
                ->groupBy('page_path')
                ->orderByDesc('views')
                ->limit(50)
                ->get();

            return ['rows' => $rows->map(static fn (object $row): array => [
                'page_path' => $row->page_path,
                'page_title' => $row->page_title,
                'views' => (int) $row->views,
                'users' => (int) $row->users,
                'views_per_user' => round(Num::safeDivide((int) $row->views, (int) $row->users), 2),
                'avg_time_seconds' => round((float) $row->avg_time, 1),
                'events' => (int) $row->events,
                'bounce_rate' => round((float) $row->bounce_rate, 1),
            ])->all()];
        }), $filters);
    }

    public function topCities(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'top_cities', $filters, function () use ($filters): array {
            $rows = DB::table('analytics_cities')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw('city, region, country')
                ->selectRaw('COALESCE(SUM(sessions),0) AS sessions, COALESCE(SUM(users),0) AS users')
                ->selectRaw('COALESCE(SUM(purchases),0) AS purchases, COALESCE(SUM(revenue),0) AS revenue')
                ->groupBy('city', 'region', 'country')
                ->orderByDesc('sessions')
                ->limit(30)
                ->get();

            return ['rows' => $rows->map(static fn (object $r): array => [
                ...(array) $r,
                'sessions' => (int) $r->sessions,
                'users' => (int) $r->users,
                'purchases' => (int) $r->purchases,
                'revenue' => (int) $r->revenue,
                'conversion_pct' => Num::pct((int) $r->purchases, (int) $r->sessions),
            ])->all()];
        }), $filters);
    }

    public function activeUsersByCountry(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'users_by_country', $filters, function () use ($filters): array {
            $rows = DB::table('analytics_cities')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw('country, COALESCE(SUM(users),0) AS users, COALESCE(SUM(revenue),0) AS revenue')
                ->groupBy('country')
                ->orderByDesc('users')
                ->limit(15)
                ->get();

            return ['rows' => $rows->map(static fn (object $r): array => [
                'country' => $r->country ?? 'Unknown',
                'users' => (int) $r->users,
                'revenue' => (int) $r->revenue,
            ])->all()];
        }), $filters);
    }

    public function productPerformance(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'product_perf', $filters, function () use ($filters): array {
            $rows = DB::table('analytics_products')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw('item_name, item_id')
                ->selectRaw('COALESCE(SUM(views),0) AS views, COALESCE(SUM(add_to_carts),0) AS atc')
                ->selectRaw('COALESCE(SUM(purchases),0) AS purchases, COALESCE(SUM(revenue),0) AS revenue')
                ->groupBy('item_name', 'item_id')
                ->orderByDesc('revenue')
                ->limit(40)
                ->get();

            return ['rows' => $rows->map(static fn (object $r): array => [
                'item_name' => $r->item_name,
                'item_id' => $r->item_id,
                'views' => (int) $r->views,
                'add_to_carts' => (int) $r->atc,
                'purchases' => (int) $r->purchases,
                'revenue' => (int) $r->revenue,
                'view_to_atc_pct' => Num::pct((int) $r->atc, (int) $r->views),
                'atc_to_purchase_pct' => Num::pct((int) $r->purchases, (int) $r->atc),
            ])->all()];
        }), $filters);
    }

    public function placements(Request $request): JsonResponse
    {
        return $this->breakdown($request, 'placement', 'placement');
    }

    public function spendByObjective(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'spend_by_objective', $filters, function () use ($filters): array {
            $rows = DB::table('ad_insights_daily as i')
                ->join('campaigns as c', 'c.id', '=', 'i.campaign_id')
                ->where('i.tenant_id', Tenant::id())
                ->where('i.breakdown_key', 'total')
                ->whereBetween('i.date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw("COALESCE(NULLIF(c.objective, ''), 'unspecified') AS objective")
                ->selectRaw('COALESCE(SUM(i.spend),0) AS spend, COALESCE(SUM(i.conversion_value),0) AS value')
                ->groupByRaw("COALESCE(NULLIF(c.objective, ''), 'unspecified')")
                ->orderByDesc('spend')
                ->get();

            return ['rows' => $rows->map(static fn (object $r): array => [
                'objective' => str_replace('_', ' ', (string) $r->objective),
                'spend' => (int) $r->spend,
                'attributed_sales' => (int) $r->value,
                'roas' => Num::ratio((int) $r->value, (int) $r->spend),
            ])->all()];
        }), $filters);
    }

    /**
     * Creative fatigue: rising frequency with falling CTR is the signature.
     */
    public function creativeFatigue(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'creative_fatigue', $filters, function () use ($filters): array {
            $mid = $filters->period->from->addDays((int) floor($filters->period->days() / 2))->toDateString();

            $rows = DB::table('ad_insights_daily as i')
                ->join('ads as a', 'a.id', '=', 'i.ad_id')
                ->leftJoin('ad_creatives as cr', 'cr.ad_id', '=', 'a.id')
                ->where('i.tenant_id', Tenant::id())
                ->where('i.breakdown_key', 'total')
                ->whereBetween('i.date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw('a.id, a.name, cr.thumbnail_url, cr.format')
                ->selectRaw('COALESCE(SUM(CASE WHEN i.date < ? THEN i.clicks END),0) AS clicks_first', [$mid])
                ->selectRaw('COALESCE(SUM(CASE WHEN i.date < ? THEN i.impressions END),0) AS impr_first', [$mid])
                ->selectRaw('COALESCE(SUM(CASE WHEN i.date >= ? THEN i.clicks END),0) AS clicks_second', [$mid])
                ->selectRaw('COALESCE(SUM(CASE WHEN i.date >= ? THEN i.impressions END),0) AS impr_second', [$mid])
                ->selectRaw('COALESCE(SUM(i.spend),0) AS spend, COALESCE(SUM(i.reach),0) AS reach')
                ->selectRaw('COALESCE(SUM(i.impressions),0) AS impressions')
                ->groupBy('a.id', 'a.name', 'cr.thumbnail_url', 'cr.format')
                ->havingRaw('SUM(i.impressions) > 1000')
                ->get();

            $creatives = $rows->map(static function (object $row): array {
                $ctrFirst = Num::pct((int) $row->clicks_first, (int) $row->impr_first);
                $ctrSecond = Num::pct((int) $row->clicks_second, (int) $row->impr_second);
                $frequency = round(Num::safeDivide((int) $row->impressions, (int) $row->reach), 2);
                $decay = $ctrFirst > 0 ? round(($ctrSecond - $ctrFirst) / $ctrFirst * 100, 1) : 0.0;

                return [
                    'ad_id' => $row->id,
                    'name' => $row->name,
                    'thumbnail_url' => $row->thumbnail_url,
                    'format' => $row->format,
                    'spend' => (int) $row->spend,
                    'frequency' => $frequency,
                    'ctr_first_half' => $ctrFirst,
                    'ctr_second_half' => $ctrSecond,
                    'ctr_decay_pct' => $decay,
                    'is_fatigued' => $decay < -20 && $frequency > 2.0,
                ];
            })->sortBy('ctr_decay_pct')->values();

            $fatigued = $creatives->where('is_fatigued', true);

            return [
                'rows' => $creatives->all(),
                'fatigued_count' => $fatigued->count(),
                'fatigued_spend' => (int) $fatigued->sum('spend'),
                'caveat' => 'Fatigue is flagged when CTR falls more than 20% between the first and second half of the window while frequency is above 2. Ads with under 1,000 impressions are excluded.',
                'verdict' => ($fatigued->isEmpty()
                    ? Verdict::good('No creative is showing fatigue in this window.')->toArray()
                    : Verdict::watch(
                        sprintf('%d creatives are fatiguing.', $fatigued->count()),
                        sprintf('%s of spend is behind creatives whose CTR is falling as frequency rises.', Money::compact((int) $fatigued->sum('spend'))),
                        'Refresh the hook — the audience has seen this too many times.',
                    )->toArray()),
            ];
        }), $filters);
    }

    public function creativeLibrary(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'creative_library', $filters, function () use ($filters): array {
            $rows = DB::table('ad_creatives as cr')
                ->join('ads as a', 'a.id', '=', 'cr.ad_id')
                ->leftJoin('ad_insights_daily as i', function ($join) use ($filters): void {
                    $join->on('i.ad_id', '=', 'a.id')
                        ->where('i.breakdown_key', 'total')
                        ->whereBetween('i.date', [$filters->period->fromDate(), $filters->period->toDate()]);
                })
                ->where('cr.tenant_id', Tenant::id())
                ->selectRaw('cr.id, cr.name, cr.thumbnail_url, cr.format, cr.title, cr.body, cr.call_to_action, a.name AS ad_name')
                ->selectRaw('COALESCE(SUM(i.spend),0) AS spend, COALESCE(SUM(i.impressions),0) AS impressions')
                ->selectRaw('COALESCE(SUM(i.clicks),0) AS clicks, COALESCE(SUM(i.conversion_value),0) AS value')
                ->selectRaw('COALESCE(SUM(i.video_views_3s),0) AS views_3s')
                ->groupBy('cr.id', 'cr.name', 'cr.thumbnail_url', 'cr.format', 'cr.title', 'cr.body', 'cr.call_to_action', 'a.name')
                ->orderByDesc('spend')
                ->limit(60)
                ->get();

            return ['rows' => $rows->map(static fn (object $r): array => [
                'id' => $r->id,
                'name' => $r->name ?? $r->ad_name,
                'thumbnail_url' => $r->thumbnail_url,
                'format' => $r->format,
                'title' => $r->title,
                'body' => $r->body,
                'call_to_action' => $r->call_to_action,
                'spend' => (int) $r->spend,
                'impressions' => (int) $r->impressions,
                'clicks' => (int) $r->clicks,
                'ctr' => Num::pct((int) $r->clicks, (int) $r->impressions),
                'attributed_sales' => (int) $r->value,
                'roas' => Num::ratio((int) $r->value, (int) $r->spend),
                'hook_rate' => Num::pct((int) $r->views_3s, (int) $r->impressions),
            ])->all()];
        }), $filters);
    }

    /**
     * Planned vs actual spend, with a month-end projection from the run rate.
     */
    public function budgetPacing(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'budget_pacing', $filters, function (): array {
            $timezone = Tenant::timezone();
            $now = now($timezone);
            $daysElapsed = (int) $now->day;
            $daysInMonth = (int) $now->daysInMonth;

            $plannedDaily = (int) DB::table('campaigns')
                ->where('tenant_id', Tenant::id())
                ->where('status', 'active')
                ->sum('daily_budget');

            $actual = (int) DB::table('ad_spend_rollup')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()])
                ->sum('spend');

            $planned = $plannedDaily * $daysElapsed;
            $projected = (int) round(Num::safeDivide($actual, $daysElapsed) * $daysInMonth);

            return [
                'planned_daily' => $plannedDaily,
                'planned_to_date' => $planned,
                'actual_to_date' => $actual,
                'variance' => $actual - $planned,
                'variance_pct' => Num::pct($actual - $planned, max($planned, 1)),
                'projected_month_end' => $projected,
                'planned_month_end' => $plannedDaily * $daysInMonth,
                'days_elapsed' => $daysElapsed,
                'days_in_month' => $daysInMonth,
                'caveat' => 'Planned spend is the sum of active campaign daily budgets. Campaigns paused mid-month still count towards the plan until they are archived.',
            ];
        }), $filters);
    }

    /** Marketing Efficiency Ratio — total revenue over total ad spend, daily. */
    public function mer(Request $request, RollupQuery $rollups): JsonResponse
    {
        $filters = $this->filters($request);
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);

        return ApiResponse::ok($this->cached('marketing', 'mer', $filters, function () use ($rollups, $filters, $benchmark): array {
            $sales = $rollups->daily($filters, ['net_sales'])->keyBy('date');
            $spend = $rollups->dailyAdSpend($filters)->groupBy('date');

            $series = $filters->period->dateKeys()->map(static function (string $date) use ($sales, $spend): array {
                $daySpend = (int) $spend->get($date, collect())->sum('spend');
                $netSales = (int) ($sales[$date]['net_sales'] ?? 0);

                return [
                    'date' => $date,
                    'net_sales' => $netSales,
                    'ad_spend' => $daySpend,
                    'mer' => Num::ratio($netSales, $daySpend),
                ];
            });

            $totalSales = (int) $series->sum('net_sales');
            $totalSpend = (int) $series->sum('ad_spend');
            $mer = Num::ratio($totalSales, $totalSpend);

            return [
                'series' => $series->all(),
                'mer' => $mer,
                'target' => (float) $benchmark->target_roas,
                'total_net_sales' => $totalSales,
                'total_ad_spend' => $totalSpend,
                'verdict' => ($mer >= $benchmark->target_roas
                    ? Verdict::good(sprintf('MER is %.2f×, at or above your %.1f× target.', $mer, $benchmark->target_roas))
                    : Verdict::watch(
                        sprintf('MER is %.2f× against a %.1f× target.', $mer, $benchmark->target_roas),
                        'Every rupee of ad spend is returning less than you planned for.',
                        'MER is the honest number — platform ROAS will look better than this and is double counting.',
                    ))->toArray(),
            ];
        }), $filters);
    }

    public function utmAnalysis(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'utm', $filters, function () use ($filters): array {
            $rows = DB::table('orders')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->whereNotNull('utm_source')
                ->selectRaw("COALESCE(NULLIF(utm_source, ''), 'unknown') AS source")
                ->selectRaw("COALESCE(NULLIF(utm_medium, ''), 'none') AS medium")
                ->selectRaw("COALESCE(NULLIF(utm_campaign, ''), '(not set)') AS campaign")
                ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(net_amount),0) AS net_sales')
                ->selectRaw('COALESCE(SUM(contribution_margin),0) AS margin')
                ->selectRaw('SUM(CASE WHEN is_first_order = 1 THEN 1 ELSE 0 END) AS new_customers')
                ->groupByRaw("COALESCE(NULLIF(utm_source, ''), 'unknown'), COALESCE(NULLIF(utm_medium, ''), 'none'), COALESCE(NULLIF(utm_campaign, ''), '(not set)')")
                ->orderByDesc('net_sales')
                ->limit(60)
                ->get();

            return ['rows' => $rows->map(static fn (object $r): array => [
                ...(array) $r,
                'orders' => (int) $r->orders,
                'net_sales' => (int) $r->net_sales,
                'margin' => (int) $r->margin,
                'margin_pct' => Num::pct((int) $r->margin, (int) $r->net_sales),
                'new_customers' => (int) $r->new_customers,
                'aov' => (int) round(Num::safeDivide((int) $r->net_sales, (int) $r->orders)),
            ])->all()];
        }), $filters);
    }

    private function breakdown(Request $request, string $breakdownKey, string $dimension): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketing', 'breakdown:'.$breakdownKey, $filters, function () use ($filters, $breakdownKey, $dimension): array {
            $rows = DB::table('ad_insights_daily')
                ->where('tenant_id', Tenant::id())
                ->where('breakdown_key', $breakdownKey)
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->whereNotNull($dimension)
                ->selectRaw("{$dimension} AS dimension")
                ->selectRaw('COALESCE(SUM(spend),0) AS spend, COALESCE(SUM(impressions),0) AS impressions')
                ->selectRaw('COALESCE(SUM(clicks),0) AS clicks, COALESCE(SUM(conversion_value),0) AS value')
                ->groupBy('dimension')
                ->orderByDesc('spend')
                ->get();

            return ['rows' => $rows->map(static fn (object $r): array => [
                'dimension' => $r->dimension,
                'spend' => (int) $r->spend,
                'impressions' => (int) $r->impressions,
                'clicks' => (int) $r->clicks,
                'ctr' => Num::pct((int) $r->clicks, (int) $r->impressions),
                'attributed_sales' => (int) $r->value,
                'roas' => Num::ratio((int) $r->value, (int) $r->spend),
            ])->all()];
        }), $filters);
    }
}
