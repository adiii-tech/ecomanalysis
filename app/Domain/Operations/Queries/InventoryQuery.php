<?php

declare(strict_types=1);

namespace App\Domain\Operations\Queries;

use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock health: days of cover, reorder signals, ABC class and the revenue
 * already lost to stockouts.
 */
class InventoryQuery
{
    /**
     * SKUs with days-of-cover below the tenant threshold, worst first.
     *
     * @return array<string, mixed>
     */
    public function critical(WidgetFilters $filters): array
    {
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);
        $rows = $this->coverage($filters)
            ->where('days_of_cover', '<', $benchmark->days_of_cover_threshold)
            ->sortBy('days_of_cover')
            ->values();

        $revenueAtRisk = (int) $rows->sum('monthly_revenue');

        return [
            'rows' => $rows->take(25)->all(),
            'count' => $rows->count(),
            'threshold_days' => $benchmark->days_of_cover_threshold,
            'revenue_at_risk' => $revenueAtRisk,
            'verdict' => ($rows->isEmpty()
                ? Verdict::good('No bestseller is close to stocking out.')
                : Verdict::bad(
                    sprintf('%d SKUs have under %d days of cover.', $rows->count(), $benchmark->days_of_cover_threshold),
                    sprintf('They generate %s a month — that revenue stops when the stock does.', Money::compact($revenueAtRisk)),
                    'Raise a purchase order for the top rows today.',
                    $revenueAtRisk,
                ))->toArray(),
        ];
    }

    /**
     * Reorder plan with ABC classification by revenue contribution.
     *
     * @return array<string, mixed>
     */
    public function reorder(WidgetFilters $filters): array
    {
        $rows = $this->coverage($filters)->sortByDesc('monthly_revenue')->values();
        $totalRevenue = (int) $rows->sum('monthly_revenue');
        $running = 0;

        $classified = $rows->map(static function (array $row) use (&$running, $totalRevenue): array {
            $running += $row['monthly_revenue'];
            $cumulative = Num::pct($running, $totalRevenue);

            return [
                ...$row,
                'cumulative_revenue_pct' => $cumulative,
                'abc_class' => match (true) {
                    $cumulative <= 80 => 'A',
                    $cumulative <= 95 => 'B',
                    default => 'C',
                },
            ];
        });

        return [
            'rows' => $classified->all(),
            'dead_stock' => $classified->where('daily_rate', 0)->where('stock', '>', 0)->values()->all(),
            'caveat' => 'Suggested quantities assume the last 30 days of sell-through continues. Seasonality is not modelled.',
        ];
    }

    /**
     * Revenue lost while a SKU had no stock. Only counted for SKUs that were
     * actually selling before they ran out, so the estimate stays grounded.
     *
     * @return array<string, mixed>
     */
    public function stockouts(WidgetFilters $filters): array
    {
        $rows = $this->coverage($filters)
            ->where('stock', '<=', 0)
            ->where('daily_rate', '>', 0)
            ->map(static function (array $row) use ($filters): array {
                $daysOutOfStock = max(0, $filters->period->days() - (int) ceil($row['units_30d'] / max($row['daily_rate'], 0.01)));

                return [
                    ...$row,
                    'days_out_of_stock' => $daysOutOfStock,
                    'estimated_lost_revenue' => (int) round($row['daily_rate'] * $daysOutOfStock * $row['selling_price']),
                ];
            })
            ->sortByDesc('estimated_lost_revenue')
            ->values();

        return [
            'rows' => $rows->all(),
            'total_lost_revenue' => (int) $rows->sum('estimated_lost_revenue'),
            'caveat' => 'Lost revenue is the SKU\'s own recent daily sell-through multiplied by days without stock. It is an estimate from your data, not a forecast.',
        ];
    }

    /**
     * Per-SKU stock, 30-day sell-through and days of cover.
     *
     * @return Collection<int, array{sku_id: mixed, sku_code: mixed, name: mixed, category: mixed, image_url: mixed, stock: int, cost_price: int, selling_price: int, stock_value: int, units_30d: int, daily_rate: float, days_of_cover: float, monthly_revenue: int, suggested_reorder_qty: int}>
     */
    public function coverage(WidgetFilters $filters): Collection
    {
        $since = now(Tenant::timezone())->subDays(30)->toDateString();
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);

        return DB::table('skus as s')
            ->leftJoin('inventory as i', 'i.sku_id', '=', 's.id')
            ->leftJoin('sku_daily_rollup as r', function ($join) use ($since): void {
                $join->on('r.sku_id', '=', 's.id')->where('r.date', '>=', $since);
            })
            ->where('s.tenant_id', Tenant::id())
            ->where('s.is_active', true)
            ->selectRaw('s.id, s.sku_code, s.name, s.category, s.image_url, s.cost_price, s.selling_price')
            ->selectRaw('COALESCE(SUM(DISTINCT i.available), 0) AS stock')
            ->selectRaw('COALESCE(SUM(r.units_sold), 0) AS units_30d')
            ->selectRaw('COALESCE(SUM(r.net_sales), 0) AS revenue_30d')
            ->groupBy('s.id', 's.sku_code', 's.name', 's.category', 's.image_url', 's.cost_price', 's.selling_price')
            ->get()
            ->map(static function (object $r) use ($benchmark): array {
                $dailyRate = round(Num::safeDivide((int) $r->units_30d, 30), 3);
                $stock = (int) $r->stock;
                $daysOfCover = $dailyRate > 0 ? round($stock / $dailyRate, 1) : ($stock > 0 ? 999.0 : 0.0);
                $reorderQty = $dailyRate > 0
                    ? max(0, (int) ceil($dailyRate * ($benchmark->days_of_cover_threshold * 2) - $stock))
                    : 0;

                return [
                    'sku_id' => $r->id,
                    'sku_code' => $r->sku_code,
                    'name' => $r->name,
                    'category' => $r->category,
                    'image_url' => $r->image_url,
                    'stock' => $stock,
                    'cost_price' => (int) $r->cost_price,
                    'selling_price' => (int) $r->selling_price,
                    'stock_value' => $stock * (int) $r->cost_price,
                    'units_30d' => (int) $r->units_30d,
                    'daily_rate' => $dailyRate,
                    'days_of_cover' => $daysOfCover,
                    'monthly_revenue' => (int) $r->revenue_30d,
                    'suggested_reorder_qty' => $reorderQty,
                ];
            });
    }

    /** @return array<string, mixed> */
    public function valuation(WidgetFilters $filters): array
    {
        $rows = DB::table('skus as s')
            ->join('inventory as i', 'i.sku_id', '=', 's.id')
            ->where('s.tenant_id', Tenant::id())
            ->selectRaw("COALESCE(NULLIF(s.category, ''), 'Uncategorised') AS category")
            ->selectRaw('COUNT(DISTINCT s.id) AS sku_count, SUM(i.available) AS units')
            ->selectRaw('SUM(GREATEST(i.available, 0) * s.cost_price) AS value_at_cost')
            ->selectRaw('SUM(GREATEST(i.available, 0) * s.selling_price) AS value_at_retail')
            ->groupBy('category')
            ->orderByDesc('value_at_cost')
            ->get();

        return [
            'rows' => $rows->all(),
            'total_at_cost' => (int) $rows->sum('value_at_cost'),
            'total_at_retail' => (int) $rows->sum('value_at_retail'),
            'total_units' => (int) $rows->sum('units'),
        ];
    }
}
