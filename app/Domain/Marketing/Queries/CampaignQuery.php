<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Queries;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * Campaign performance with an automatic Scale / Hold / Cut verdict computed
 * against both the account average and the tenant's configured target.
 */
class CampaignQuery
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /** @return array<string, mixed> */
    public function table(WidgetFilters $filters): array
    {
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);

        $rows = DB::table('ad_insights_daily as i')
            ->join('campaigns as c', 'c.id', '=', 'i.campaign_id')
            ->where('i.tenant_id', Tenant::id())
            ->where('i.breakdown_key', 'total')
            ->whereBetween('i.date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('c.id, c.name, c.platform, c.objective, c.status, c.daily_budget')
            ->selectRaw('COALESCE(SUM(i.spend),0) AS spend, COALESCE(SUM(i.impressions),0) AS impressions')
            ->selectRaw('COALESCE(SUM(i.clicks),0) AS clicks, COALESCE(SUM(i.conversions),0) AS conversions')
            ->selectRaw('COALESCE(SUM(i.conversion_value),0) AS conversion_value')
            ->groupBy('c.id', 'c.name', 'c.platform', 'c.objective', 'c.status', 'c.daily_budget')
            ->havingRaw('SUM(i.spend) > 0')
            ->orderByDesc('spend')
            ->get();

        $totalSpend = (int) $rows->sum('spend');
        $totalValue = (int) $rows->sum('conversion_value');
        $accountRoas = Num::ratio($totalValue, $totalSpend);

        $campaigns = $rows->map(function (object $row) use ($benchmark, $accountRoas, $totalSpend): array {
            $spend = (int) $row->spend;
            $value = (int) $row->conversion_value;
            $roas = Num::ratio($value, $spend);
            $verdict = Verdict::forRoas($roas, (float) $benchmark->target_roas, $accountRoas, $spend);

            return [
                'id' => $row->id,
                'name' => $row->name,
                'platform' => $row->platform,
                'objective' => $row->objective,
                'status' => $row->status,
                'spend' => $spend,
                'impressions' => (int) $row->impressions,
                'clicks' => (int) $row->clicks,
                'ctr' => Num::pct((int) $row->clicks, (int) $row->impressions),
                'cpc' => (int) round(Num::safeDivide($spend, (int) $row->clicks)),
                'cpm' => (int) round(Num::safeDivide($spend * 1000, (int) $row->impressions)),
                'conversions' => (int) $row->conversions,
                'attributed_sales' => $value,
                'roas' => $roas,
                'cac' => (int) round(Num::safeDivide($spend, (int) $row->conversions)),
                'spend_share_pct' => Num::pct($spend, $totalSpend),
                'verdict' => $verdict->toArray(),
            ];
        })->all();

        return [
            'rows' => $campaigns,
            'account_roas' => $accountRoas,
            'target_roas' => (float) $benchmark->target_roas,
            'total_spend' => $totalSpend,
            'total_attributed_sales' => $totalValue,
            'caveat' => 'ROAS here is platform-reported attribution. The attribution-gap widget compares it with orders actually recorded in your store.',
            'verdict' => $this->summaryVerdict($campaigns, $accountRoas, (float) $benchmark->target_roas)->toArray(),
        ];
    }

    /**
     * Best performer / needs attention / trend / opportunity, written from the
     * data rather than from a template.
     *
     * @return list<array<string, mixed>>
     */
    public function insightCards(WidgetFilters $filters): array
    {
        $table = $this->table($filters);
        $rows = collect($table['rows']);

        if ($rows->isEmpty()) {
            return [];
        }

        $best = $rows->sortByDesc('roas')->first();
        $worst = $rows->where('spend', '>', 0)->sortBy('roas')->first();

        $current = $this->rollups->adSpend($filters);
        $previous = $this->rollups->adSpend($filters->previous());
        $spendDelta = Num::pct($current['total'] - $previous['total'], max($previous['total'], 1));

        $cards = [
            [
                'kind' => 'best_performer',
                'title' => 'Best performer',
                'headline' => $best['name'],
                'body' => sprintf('%.2f× ROAS on %s of spend — %.0f%% above the account average.',
                    $best['roas'], Money::compact($best['spend']), Num::pct($best['roas'] - $table['account_roas'], max($table['account_roas'], 0.01))),
                'tone' => 'good',
            ],
            [
                'kind' => 'needs_attention',
                'title' => 'Needs attention',
                'headline' => $worst['name'],
                'body' => sprintf('%.2f× ROAS on %s of spend against a %.1f× target.', $worst['roas'], Money::compact($worst['spend']), $table['target_roas']),
                'tone' => $worst['roas'] < $table['target_roas'] * 0.6 ? 'bad' : 'warn',
            ],
            [
                'kind' => 'trend',
                'title' => 'Spend trend',
                'headline' => sprintf('%s%.0f%% vs previous period', $spendDelta >= 0 ? '+' : '', $spendDelta),
                'body' => sprintf('%s spent this period against %s in the previous one.',
                    Money::compact($current['total']), Money::compact($previous['total'])),
                'tone' => 'neutral',
            ],
        ];

        // Only offer a reallocation when there is a real gap to exploit.
        if ($worst['roas'] < $table['target_roas'] && $best['roas'] > $table['target_roas'] && $worst['spend'] > 0) {
            $shift = (int) round($worst['spend'] * 0.5);
            $cards[] = [
                'kind' => 'opportunity',
                'title' => 'Opportunity',
                'headline' => sprintf('Move %s from %s to %s', Money::compact($shift), $worst['name'], $best['name']),
                'body' => sprintf('At their current rates that swap is worth about %s more in attributed sales.',
                    Money::compact((int) round($shift * ($best['roas'] - $worst['roas'])))),
                'tone' => 'good',
                'impact_amount' => (int) round($shift * ($best['roas'] - $worst['roas'])),
            ];
        }

        return $cards;
    }

    /**
     * Blended vs attributed: the gap between what the platforms claim and what
     * the store actually recorded.
     *
     * @return array<string, mixed>
     */
    public function attributionGap(WidgetFilters $filters): array
    {
        $ads = $this->rollups->adSpend($filters);
        $totals = $this->rollups->totals($filters);

        $platformClaimed = $ads['conversion_value'];
        $actual = $totals['net_sales'];
        $gap = $platformClaimed - $actual;

        return [
            'platform_reported_sales' => $platformClaimed,
            'platform_reported_orders' => $ads['conversions'],
            'store_net_sales' => $actual,
            'store_orders' => $totals['orders_count'],
            'gap_amount' => $gap,
            'gap_pct' => Num::pct($gap, max($actual, 1)),
            'blended_roas' => Num::ratio($actual, $ads['total']),
            'attributed_roas' => Num::ratio($platformClaimed, $ads['total']),
            'mer' => Num::ratio($actual, $ads['total']),
            'caveat' => 'Platforms count a conversion inside their own attribution window and often claim the same order twice across networks. Store net sales is the only figure that reconciles to your bank.',
            'verdict' => ($gap > $actual * 0.25
                ? Verdict::watch(
                    sprintf('Platforms claim %.0f%% more sales than your store recorded.', Num::pct($gap, max($actual, 1))),
                    sprintf('%s claimed vs %s actually net.', Money::compact($platformClaimed), Money::compact($actual)),
                    'Budget on blended ROAS, not platform ROAS — the platform number is double-counting.',
                )
                : Verdict::good('Platform-reported sales are broadly in line with your store.'))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function trend(WidgetFilters $filters): array
    {
        $spend = $this->rollups->dailyAdSpend($filters)->groupBy('date');
        $sales = $this->rollups->daily($filters, ['net_sales', 'orders_count'])->keyBy('date');

        $series = $filters->period->dateKeys()->map(function (string $date) use ($spend, $sales): array {
            $daySpend = $spend->get($date, collect());
            $meta = (int) $daySpend->where('platform', 'meta')->sum('spend');
            $google = (int) $daySpend->where('platform', 'google_ads')->sum('spend');
            $netSales = (int) ($sales[$date]['net_sales'] ?? 0);
            $total = $meta + $google;

            return [
                'date' => $date,
                'meta_spend' => $meta,
                'google_spend' => $google,
                'total_spend' => $total,
                'net_sales' => $netSales,
                'blended_roas' => Num::ratio($netSales, $total),
            ];
        });

        return [
            'series' => $series->all(),
            'anomalies' => $this->anomalies($series->all()),
        ];
    }

    /**
     * Statistical anomaly flags on daily spend — z-score against the window's
     * own mean, so it adapts to each brand's scale.
     *
     * @param  list<array<string, mixed>>  $series
     * @return list<array<string, mixed>>
     */
    private function anomalies(array $series): array
    {
        $values = array_map(static fn (array $row): float => (float) $row['total_spend'], $series);
        $mean = Num::mean($values);
        $stdDev = Num::stdDev($values);

        if ($stdDev < 1) {
            return [];
        }

        $flags = [];
        foreach ($series as $row) {
            $z = ((float) $row['total_spend'] - $mean) / $stdDev;

            if (abs($z) >= 2.0) {
                $flags[] = [
                    'date' => $row['date'],
                    'metric' => 'ad_spend',
                    'value' => $row['total_spend'],
                    'expected' => (int) round($mean),
                    'z_score' => round($z, 2),
                    'direction' => $z > 0 ? 'up' : 'down',
                    'note' => sprintf('Spend was %.1f standard deviations %s the period mean.', abs($z), $z > 0 ? 'above' : 'below'),
                ];
            }
        }

        return $flags;
    }

    /** @param list<array<string, mixed>> $campaigns */
    private function summaryVerdict(array $campaigns, float $accountRoas, float $target): Verdict
    {
        $collection = collect($campaigns);

        if ($collection->isEmpty()) {
            return Verdict::neutral('No campaign spent in this window.');
        }

        $toCut = $collection->filter(fn (array $c): bool => $c['verdict']['status'] === Verdict::CUT);
        $wastedSpend = (int) $toCut->sum('spend');

        if ($toCut->isNotEmpty()) {
            return Verdict::bad(
                sprintf('%d campaign%s should be cut.', $toCut->count(), $toCut->count() === 1 ? '' : 's'),
                sprintf('They hold %s of spend at below %.1f× ROAS.', Money::compact($wastedSpend), $target * 0.6),
                'Pause them and move the budget to whatever is marked Scale.',
                $wastedSpend,
            );
        }

        return $accountRoas >= $target
            ? Verdict::good(sprintf('Account ROAS is %.2f×, at or above your %.1f× target.', $accountRoas, $target))
            : Verdict::watch(
                sprintf('Account ROAS is %.2f× against a %.1f× target.', $accountRoas, $target),
                'No single campaign is bad enough to cut — the gap is spread across the account.',
                'Test creative before adding budget.',
            );
    }
}
