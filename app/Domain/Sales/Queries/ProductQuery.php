<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductQuery
{
    /** @return Collection<int, \stdClass> */
    public function topSkus(WidgetFilters $filters, int $limit = 20, string $orderBy = 'net_sales'): Collection
    {
        return $this->base($filters)
            ->join('skus as s', 's.id', '=', 'r.sku_id')
            ->selectRaw('s.id, s.sku_code, s.name, s.category, s.image_url, s.selling_price, s.cost_price')
            ->selectRaw('SUM(r.units_sold) AS units, SUM(r.orders_count) AS orders, SUM(r.net_sales) AS net_sales, SUM(r.cogs) AS cogs, SUM(r.margin) AS margin, SUM(r.returned_units) AS returned_units')
            ->groupBy('s.id', 's.sku_code', 's.name', 's.category', 's.image_url', 's.selling_price', 's.cost_price')
            ->orderByDesc($orderBy)
            ->limit($limit)
            ->get()
            ->map(static fn (object $r): object => tap($r, static function (object $row): void {
                $row->margin_pct = Num::pct((int) $row->margin, (int) $row->net_sales);
                $row->return_rate = Num::pct((int) $row->returned_units, (int) $row->units);
            }));
    }

    /** @return array<string, mixed> */
    public function topCategories(WidgetFilters $filters, int $limit = 8): array
    {
        $rows = $this->base($filters)
            ->join('skus as s', 's.id', '=', 'r.sku_id')
            ->selectRaw("COALESCE(NULLIF(s.category, ''), 'Uncategorised') AS category")
            ->selectRaw('SUM(r.units_sold) AS units, SUM(r.net_sales) AS net_sales, SUM(r.margin) AS margin')
            ->groupBy('category')
            ->orderByDesc('net_sales')
            ->limit($limit)
            ->get();

        $total = (int) $rows->sum('net_sales');

        return [
            'rows' => $rows->map(static fn (object $r): array => [
                'category' => $r->category,
                'units' => (int) $r->units,
                'net_sales' => (int) $r->net_sales,
                'margin_pct' => Num::pct((int) $r->margin, (int) $r->net_sales),
                'share_pct' => Num::pct((int) $r->net_sales, $total),
            ])->all(),
            'total' => $total,
        ];
    }

    /**
     * Which 20% of SKUs make 80% of the profit — ranked by margin, with the
     * running cumulative share that makes the Pareto point obvious.
     *
     * @return array<string, mixed>
     */
    public function pareto(WidgetFilters $filters): array
    {
        $rows = $this->base($filters)
            ->join('skus as s', 's.id', '=', 'r.sku_id')
            ->selectRaw('s.id, s.sku_code, s.name')
            ->selectRaw('SUM(r.margin) AS margin, SUM(r.net_sales) AS net_sales, SUM(r.units_sold) AS units')
            ->groupBy('s.id', 's.sku_code', 's.name')
            ->havingRaw('SUM(r.margin) > 0')
            ->orderByDesc('margin')
            ->get();

        $totalMargin = (int) $rows->sum('margin');
        $running = 0;
        $paretoIndex = null;

        $ranked = $rows->values()->map(static function (object $r, int $index) use ($totalMargin, &$running, &$paretoIndex): array {
            $running += (int) $r->margin;
            $cumulative = Num::pct($running, $totalMargin);

            if ($paretoIndex === null && $cumulative >= 80) {
                $paretoIndex = $index + 1;
            }

            return [
                'rank' => $index + 1,
                'sku_id' => $r->id,
                'sku_code' => $r->sku_code,
                'name' => $r->name,
                'units' => (int) $r->units,
                'net_sales' => (int) $r->net_sales,
                'margin' => (int) $r->margin,
                'cumulative_pct' => $cumulative,
            ];
        });

        $skuCount = $ranked->count();
        $paretoIndex ??= $skuCount;

        return [
            'rows' => $ranked->all(),
            'total_margin' => $totalMargin,
            'sku_count' => $skuCount,
            'pareto_sku_count' => $paretoIndex,
            'pareto_share_pct' => Num::pct($paretoIndex, $skuCount),
            'verdict' => ($skuCount === 0
                ? Verdict::neutral('No profitable SKU in this window.')
                : Verdict::neutral(
                    sprintf('%d of %d SKUs (%.0f%%) make 80%% of your profit.', $paretoIndex, $skuCount, Num::pct($paretoIndex, $skuCount)),
                    'Everything below the line is working capital that could be funding the top of it.',
                ))->toArray(),
        ];
    }

    /**
     * SKUs that sold nothing in the window but still hold stock — dead capital.
     *
     * @return array<string, mixed>
     */
    public function zeroOrderSkus(WidgetFilters $filters, int $limit = 100): array
    {
        $soldSkuIds = $this->base($filters)->distinct()->pluck('r.sku_id');

        $rows = DB::table('skus as s')
            ->leftJoin('inventory as i', 'i.sku_id', '=', 's.id')
            ->where('s.tenant_id', Tenant::id())
            ->where('s.is_active', true)
            ->whereNotIn('s.id', $soldSkuIds->all() ?: [0])
            ->selectRaw('s.id, s.sku_code, s.name, s.category, s.cost_price, s.selling_price, s.image_url')
            ->selectRaw('COALESCE(SUM(i.available), 0) AS stock')
            ->selectRaw('COALESCE(SUM(i.available), 0) * s.cost_price AS capital_held')
            ->groupBy('s.id', 's.sku_code', 's.name', 's.category', 's.cost_price', 's.selling_price', 's.image_url')
            ->orderByDesc('capital_held')
            ->limit($limit)
            ->get();

        $capital = (int) $rows->sum('capital_held');

        return [
            'rows' => $rows->all(),
            'sku_count' => $rows->count(),
            'capital_held' => $capital,
            'verdict' => ($rows->isEmpty()
                ? Verdict::good('Every active SKU sold at least once in this window.')
                : Verdict::watch(
                    sprintf('%d SKUs sold nothing and hold %s of stock at cost.', $rows->count(), Money::compact($capital)),
                    'That capital is sitting still while your top SKUs could be using it.',
                    'Discount, bundle or write off the worst offenders.',
                ))->toArray(),
        ];
    }

    /**
     * The full margin chain per SKU: gross down to contribution, with each
     * deduction named. Discounts and the non-fee cost block are residuals of
     * the rollup, which is why they are labelled as such rather than being
     * presented as separately measured figures.
     *
     * @return Collection<int, array{sku_code: mixed, name: mixed, mrp: int, units: int, gross_sales: int, discounts: int, returned_amount: int, net_sales: int, cogs: int, gross_profit: int, fees: int, other_costs: int, margin: int, margin_pct: float}>
     */
    public function marginChain(WidgetFilters $filters, int $limit = 5000): Collection
    {
        return $this->base($filters)
            ->join('skus as s', 's.id', '=', 'r.sku_id')
            ->selectRaw('s.sku_code, s.name, s.selling_price')
            ->selectRaw('SUM(r.units_sold) AS units, SUM(r.gross_sales) AS gross_sales, SUM(r.net_sales) AS net_sales')
            ->selectRaw('SUM(r.returned_amount) AS returned_amount, SUM(r.cogs) AS cogs, SUM(r.fees) AS fees, SUM(r.margin) AS margin')
            ->groupBy('s.id', 's.sku_code', 's.name', 's.selling_price')
            ->orderByDesc('net_sales')
            ->limit($limit)
            ->get()
            ->map(static function (object $r): array {
                $gross = (int) $r->gross_sales;
                $net = (int) $r->net_sales;
                $returns = (int) $r->returned_amount;
                $cogs = (int) $r->cogs;
                $fees = (int) $r->fees;
                $margin = (int) $r->margin;

                return [
                    'sku_code' => $r->sku_code,
                    'name' => $r->name,
                    'mrp' => (int) $r->selling_price,
                    'units' => (int) $r->units,
                    'gross_sales' => $gross,
                    'discounts' => max(0, $gross - $returns - $net),
                    'returned_amount' => $returns,
                    'net_sales' => $net,
                    'cogs' => $cogs,
                    'gross_profit' => $net - $cogs,
                    'fees' => $fees,
                    'other_costs' => max(0, $net - $cogs - $fees - $margin),
                    'margin' => $margin,
                    'margin_pct' => Num::pct($margin, $net),
                ];
            });
    }

    private function base(WidgetFilters $filters): Builder
    {
        $query = DB::table('sku_daily_rollup as r')
            ->where('r.tenant_id', Tenant::id())
            ->whereBetween('r.date', [$filters->period->fromDate(), $filters->period->toDate()]);

        if ($filters->channelIds !== []) {
            $query->whereIn('r.channel_id', $filters->channelIds);
        }

        return $query;
    }
}
