<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Services;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Period;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the metrics an alert rule can watch.
 *
 * A rule is a metric, a comparison and a window. Some metrics are a single
 * number for the whole business; others are per-dimension (per state, per SKU,
 * per campaign) and fire once for the worst offender, which is why each
 * resolver returns a list of (dimension, value) pairs rather than a scalar.
 */
class MetricResolver
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /**
     * @return array<string, array{label: string, unit: string, dimension: string|null, description: string, higher_is_worse: bool}>
     */
    public static function catalogue(): array
    {
        return [
            'rto_rate_by_state' => [
                'label' => 'RTO % by state', 'unit' => 'percent', 'dimension' => 'state',
                'description' => 'Share of orders in a state that came back undelivered.', 'higher_is_worse' => true,
            ],
            'return_rate' => [
                'label' => 'Return rate', 'unit' => 'percent', 'dimension' => null,
                'description' => 'Customer returns and RTO as a share of invoiced orders.', 'higher_is_worse' => true,
            ],
            'contribution_margin_pct' => [
                'label' => 'Contribution margin %', 'unit' => 'percent', 'dimension' => null,
                'description' => 'Net sales minus COGS, fees and logistics, as a share of net sales.', 'higher_is_worse' => false,
            ],
            'net_sales' => [
                'label' => 'Net sales', 'unit' => 'currency', 'dimension' => null,
                'description' => 'Revenue you keep after returns and RTO.', 'higher_is_worse' => false,
            ],
            'campaign_roas' => [
                'label' => 'Campaign ROAS', 'unit' => 'ratio', 'dimension' => 'campaign',
                'description' => 'Attributed sales divided by spend, per campaign.', 'higher_is_worse' => false,
            ],
            'ad_spend' => [
                'label' => 'Ad spend', 'unit' => 'currency', 'dimension' => null,
                'description' => 'Total spend across every connected ad platform.', 'higher_is_worse' => true,
            ],
            'days_of_cover' => [
                'label' => 'Days of stock cover', 'unit' => 'days', 'dimension' => 'sku',
                'description' => 'How long current stock lasts at the recent sell-through rate.', 'higher_is_worse' => false,
            ],
            'unshipped_age_days' => [
                'label' => 'Oldest unshipped order (days)', 'unit' => 'days', 'dimension' => null,
                'description' => 'Age of the oldest order still waiting to be dispatched.', 'higher_is_worse' => true,
            ],
            'loss_making_orders' => [
                'label' => 'Loss-making orders', 'unit' => 'number', 'dimension' => null,
                'description' => 'Orders whose contribution margin is negative.', 'higher_is_worse' => true,
            ],
        ];
    }

    public static function supports(string $metric): bool
    {
        return array_key_exists($metric, self::catalogue());
    }

    /**
     * @return list<array{dimension: string|null, value: float, context: array<string, mixed>}>
     */
    public function resolve(string $metric, int $windowDays): array
    {
        $filters = new WidgetFilters(
            Period::make(
                now(Tenant::timezone())->subDays($windowDays - 1)->toDateString(),
                now(Tenant::timezone())->toDateString(),
                Tenant::timezone(),
            ),
        );

        return match ($metric) {
            'rto_rate_by_state' => $this->rtoByState($filters),
            'return_rate' => $this->scalar(fn (array $t): float => Num::pct($t['returned_orders'] + $t['rto_orders'], $t['invoiced_orders']), $filters),
            'contribution_margin_pct' => $this->scalar(fn (array $t): float => Num::pct($t['contribution_margin'], $t['net_sales']), $filters),
            'net_sales' => $this->scalar(static fn (array $t): float => (float) $t['net_sales'], $filters),
            'loss_making_orders' => $this->scalar(static fn (array $t): float => (float) $t['loss_orders'], $filters),
            'ad_spend' => [[
                'dimension' => null,
                'value' => (float) $this->rollups->adSpend($filters)['total'],
                'context' => [],
            ]],
            'campaign_roas' => $this->campaignRoas($filters),
            'days_of_cover' => $this->daysOfCover(),
            'unshipped_age_days' => $this->oldestUnshipped(),
            default => [],
        };
    }

    /**
     * @param  callable(array<string, int>): float  $compute
     * @return list<array{dimension: string|null, value: float, context: array<string, mixed>}>
     */
    private function scalar(callable $compute, WidgetFilters $filters): array
    {
        $totals = $this->rollups->totals($filters);

        return [[
            'dimension' => null,
            'value' => $compute($totals),
            'context' => ['orders' => $totals['orders_count'], 'net_sales' => Money::format($totals['net_sales'])],
        ]];
    }

    /** @return list<array{dimension: string|null, value: float, context: array<string, mixed>}> */
    private function rtoByState(WidgetFilters $filters): array
    {
        return DB::table('state_daily_rollup')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('state, SUM(orders_count) AS orders, SUM(rto_count) AS rto')
            ->groupBy('state')
            // Below ten orders an RTO rate is noise, and alerting on noise
            // trains people to ignore alerts.
            ->havingRaw('SUM(orders_count) >= 10')
            ->get()
            ->map(static fn (object $row): array => [
                'dimension' => $row->state,
                'value' => Num::pct((int) $row->rto, (int) $row->orders),
                'context' => ['orders' => (int) $row->orders, 'rto_events' => (int) $row->rto],
            ])
            ->all();
    }

    /** @return list<array{dimension: string|null, value: float, context: array<string, mixed>}> */
    private function campaignRoas(WidgetFilters $filters): array
    {
        return DB::table('ad_insights_daily as i')
            ->join('campaigns as c', 'c.id', '=', 'i.campaign_id')
            ->where('i.tenant_id', Tenant::id())
            ->where('i.breakdown_key', 'total')
            ->where('c.status', 'active')
            ->whereBetween('i.date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('c.name, COALESCE(SUM(i.spend),0) AS spend, COALESCE(SUM(i.conversion_value),0) AS value')
            ->groupBy('c.name')
            ->havingRaw('SUM(i.spend) > 0')
            ->get()
            ->map(static fn (object $row): array => [
                'dimension' => $row->name,
                'value' => Num::ratio((int) $row->value, (int) $row->spend),
                'context' => ['spend' => Money::format((int) $row->spend)],
            ])
            ->all();
    }

    /** @return list<array{dimension: string|null, value: float, context: array<string, mixed>}> */
    private function daysOfCover(): array
    {
        $since = now(Tenant::timezone())->subDays(30)->toDateString();

        return DB::table('skus as s')
            ->leftJoin('inventory as i', 'i.sku_id', '=', 's.id')
            ->leftJoin('sku_daily_rollup as r', function ($join) use ($since): void {
                $join->on('r.sku_id', '=', 's.id')->where('r.date', '>=', $since);
            })
            ->where('s.tenant_id', Tenant::id())
            ->where('s.is_active', true)
            ->selectRaw('s.sku_code, COALESCE(SUM(DISTINCT i.available),0) AS stock, COALESCE(SUM(r.units_sold),0) AS units')
            ->groupBy('s.sku_code')
            // Only SKUs that are actually selling can run out in a meaningful way.
            ->havingRaw('COALESCE(SUM(r.units_sold),0) > 0')
            ->get()
            ->map(static function (object $row): array {
                $rate = Num::safeDivide((int) $row->units, 30);

                return [
                    'dimension' => $row->sku_code,
                    'value' => $rate > 0 ? round((int) $row->stock / $rate, 1) : 999.0,
                    'context' => ['stock' => (int) $row->stock, 'units_30d' => (int) $row->units],
                ];
            })
            ->all();
    }

    /** @return list<array{dimension: string|null, value: float, context: array<string, mixed>}> */
    private function oldestUnshipped(): array
    {
        $oldest = DB::table('orders')
            ->where('tenant_id', Tenant::id())
            ->whereIn('status', ['placed', 'confirmed'])
            ->min('placed_at');

        return [[
            'dimension' => null,
            'value' => $oldest === null ? 0.0 : round(now()->diffInHours($oldest, absolute: true) / 24, 1),
            'context' => [
                'unshipped_orders' => DB::table('orders')
                    ->where('tenant_id', Tenant::id())
                    ->whereIn('status', ['placed', 'confirmed'])
                    ->count(),
            ],
        ]];
    }
}
