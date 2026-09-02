<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Operations\Queries\InventoryQuery;
use App\Domain\Sales\Queries\ProductQuery;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Sku;
use App\Models\SkuCostHistory;
use App\Support\Facades\Tenant;
use App\Support\Metric;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    use ResolvesFilters;

    public function kpis(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('catalog', 'kpis', $filters, function () use ($inventory, $filters): array {
            $coverage = $inventory->coverage($filters);
            $valuation = $inventory->valuation($filters);
            $selling = $coverage->where('units_30d', '>', 0);

            return [
                (new Metric('active_skus', 'Active SKUs', (float) $coverage->count(), null, 'number'))->toArray(),
                (new Metric('selling_skus', 'Selling SKUs', (float) $selling->count(), null, 'number', true,
                    'SKUs with at least one unit sold in the last 30 days.'))->toArray()
                    + ['badge' => Num::pct($selling->count(), $coverage->count()).'% of catalog'],
                (new Metric('out_of_stock', 'Out of Stock', (float) $coverage->where('stock', '<=', 0)->count(), null, 'number', false))->toArray(),
                (new Metric('stock_value', 'Stock at Cost', (float) $valuation['total_at_cost'], null, 'currency'))->toArray(),
                (new Metric('stock_retail', 'Stock at Retail', (float) $valuation['total_at_retail'], null, 'currency'))->toArray(),
                (new Metric('avg_cover', 'Median Days Cover',
                    Num::median($selling->pluck('days_of_cover')->map(static fn ($v): float => min((float) $v, 365))->all()),
                    null, 'days', true, 'Capped at 365 days so a single never-selling SKU cannot skew it.'))->toArray(),
            ];
        }), $filters);
    }

    public function products(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('catalog', 'products', $filters, function () use ($inventory, $filters): array {
            $margins = DB::table('sku_daily_rollup')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw('sku_id, COALESCE(SUM(net_sales),0) AS net_sales, COALESCE(SUM(margin),0) AS margin')
                ->groupBy('sku_id')
                ->get()
                ->keyBy('sku_id');

            return [
                'rows' => $inventory->coverage($filters)->map(static function (array $row) use ($margins): array {
                    $sales = $margins->get($row['sku_id']);

                    return [
                        ...$row,
                        'period_net_sales' => (int) ($sales->net_sales ?? 0),
                        'period_margin' => (int) ($sales->margin ?? 0),
                        'margin_pct' => Num::pct((int) ($sales->margin ?? 0), (int) ($sales->net_sales ?? 0)),
                        'markup_pct' => $row['cost_price'] > 0
                            ? Num::pct($row['selling_price'] - $row['cost_price'], $row['cost_price'])
                            : null,
                    ];
                })->values()->all(),
            ];
        }), $filters);
    }

    public function bestSellers(Request $request, ProductQuery $products): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            ['rows' => $this->cached('catalog', 'best_sellers', $filters, fn (): array => $products->topSkus($filters, 25, 'units')->all())],
            $filters,
        );
    }

    /**
     * Slow movers: still stocked, barely selling. Ranked by capital tied up,
     * because that is what makes a slow SKU actually expensive.
     */
    public function slowMovers(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('catalog', 'slow_movers', $filters, function () use ($inventory, $filters): array {
            $rows = $inventory->coverage($filters)
                ->filter(static fn (array $row): bool => $row['stock'] > 0 && $row['days_of_cover'] > 90)
                ->sortByDesc('stock_value')
                ->values();

            $capital = (int) $rows->sum('stock_value');

            return [
                'rows' => $rows->all(),
                'count' => $rows->count(),
                'capital_held' => $capital,
                'caveat' => 'A SKU counts as slow when its current stock would take more than 90 days to sell at its recent rate.',
                'verdict' => ($rows->isEmpty()
                    ? Verdict::good('Nothing is sitting on the shelf for more than a quarter.')
                    : Verdict::watch(
                        sprintf('%d SKUs hold more than 90 days of cover.', $rows->count()),
                        sprintf('%s of working capital is tied up in stock that is barely moving.', Money::compact($capital)),
                        'Discount or bundle the worst offenders before reordering anything else.',
                        $capital,
                    ))->toArray(),
            ];
        }), $filters);
    }

    /** Per-SKU margin, ranked — where the profit actually is. */
    public function margin(Request $request, ProductQuery $products): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('catalog', 'margin', $filters, function () use ($products, $filters): array {
            $rows = $products->topSkus($filters, 500, 'margin');
            $losers = $rows->filter(static fn (object $r): bool => (int) $r->margin < 0);

            return [
                'rows' => $rows->map(static fn (object $r): array => (array) $r)->all(),
                'loss_making_count' => $losers->count(),
                'verdict' => ($losers->isEmpty()
                    ? Verdict::good('Every SKU that sold made money.')
                    : Verdict::bad(
                        sprintf('%d SKUs lost money on every sale.', $losers->count()),
                        sprintf('They cost %s in contribution.', Money::compact(abs((int) $losers->sum('margin')))),
                        'Either raise the price, renegotiate cost, or stop selling them.',
                        abs((int) $losers->sum('margin')),
                    ))->toArray(),
            ];
        }), $filters);
    }

    /**
     * COGS editor. Cost changes are versioned rather than overwritten, so
     * historical margin stays correct for orders already placed.
     */
    public function updateCost(Request $request, int $sku): JsonResponse
    {
        $validated = $request->validate([
            'cost_price' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $model = Sku::query()->find($sku);

        if ($model === null) {
            return ApiResponse::error('SKU not found.', 404);
        }

        $costInPaise = Money::fromRupees($validated['cost_price']);
        $effectiveFrom = $validated['effective_from'] ?? now(Tenant::timezone())->toDateString();

        DB::transaction(function () use ($model, $costInPaise, $effectiveFrom, $validated): void {
            SkuCostHistory::query()->updateOrCreate(
                ['tenant_id' => $model->tenant_id, 'sku_id' => $model->id, 'effective_from' => $effectiveFrom],
                ['cost_price' => $costInPaise, 'note' => $validated['note'] ?? null],
            );

            // The SKU column carries the current cost; history carries the rest.
            $model->forceFill(['cost_price' => $costInPaise])->save();

            activity('catalog')
                ->performedOn($model)
                ->withProperties([
                    'sku_code' => $model->sku_code,
                    'cost_price' => $costInPaise,
                    'effective_from' => $effectiveFrom,
                ])
                ->log('sku.cost_updated');
        });

        return ApiResponse::ok(
            ['sku_id' => $model->id, 'cost_price' => $costInPaise],
            message: 'Cost saved. Re-run the rollups to see margin update for past orders.',
        );
    }

    public function costHistory(int $sku): JsonResponse
    {
        $rows = SkuCostHistory::query()
            ->where('sku_id', $sku)
            ->orderByDesc('effective_from')
            ->get()
            ->map(static fn (SkuCostHistory $row): array => [
                'id' => $row->id,
                'cost_price' => $row->cost_price,
                'effective_from' => $row->effective_from->toDateString(),
                'note' => $row->note,
            ]);

        return ApiResponse::ok(['rows' => $rows->all()]);
    }
}
