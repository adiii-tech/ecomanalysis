<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Operations\Queries\InventoryQuery;
use App\Domain\Operations\Queries\ReturnsQuery;
use App\Domain\Sales\Queries\ChannelQuery;
use App\Domain\Sales\Queries\GeoQuery;
use App\Domain\Sales\Queries\KpiQuery;
use App\Domain\Sales\Queries\OrderQuery;
use App\Domain\Sales\Queries\PaymentModeQuery;
use App\Domain\Sales\Queries\ProductQuery;
use App\Domain\Sales\Queries\SalesSummaryQuery;
use App\Enums\OrderStatus;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Facades\Tenant;
use App\Support\Num;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    use ResolvesFilters;

    public function kpis(Request $request, KpiQuery $kpis): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'kpis', $filters, fn (): array => $kpis->marketplace($filters)), $filters);
    }

    /**
     * Deliberately independent of the global date filter — a "right now"
     * snapshot compared with yesterday.
     */
    public function todaySnapshot(Request $request, KpiQuery $kpis): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $kpis->todaySnapshot($filters, Tenant::timezone()),
            $filters,
            ['ignores_date_filter' => true],
        );
    }

    public function salesSummary(Request $request, SalesSummaryQuery $summary): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'sales_summary', $filters, fn (): array => $summary->handle($filters)), $filters);
    }

    public function revenueTrend(Request $request, ChannelQuery $channels): JsonResponse
    {
        $filters = $this->filters($request);
        $metric = $request->string('metric', 'invoiced_sales')->toString();
        $metric = in_array($metric, ['invoiced_sales', 'net_sales', 'items_count'], true) ? $metric : 'invoiced_sales';

        return ApiResponse::ok($this->cached('marketplace', 'trend:'.$metric, $filters, fn (): array => $channels->daily($filters, $metric)), $filters);
    }

    public function channelComparison(Request $request, ChannelQuery $channels): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'channel_comparison', $filters, fn (): array => $channels->mix($filters)), $filters);
    }

    public function topCategories(Request $request, ProductQuery $products): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'top_categories', $filters, fn (): array => $products->topCategories($filters, 12)), $filters);
    }

    public function topProducts(Request $request, ProductQuery $products): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'top_products', $filters, function () use ($products, $filters): array {
            $rows = $products->topSkus($filters, 25);
            $total = (int) $rows->sum('net_sales');

            return ['rows' => $rows->map(static fn (object $r): array => [
                ...(array) $r,
                'share_pct' => Num::pct((int) $r->net_sales, $total),
            ])->all(), 'total' => $total];
        }), $filters);
    }

    /** SKU rows × marketplace columns. */
    public function topProductsByChannel(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'products_by_channel', $filters, function () use ($filters): array {
            $rows = DB::table('sku_daily_rollup as r')
                ->join('skus as s', 's.id', '=', 'r.sku_id')
                ->join('channels as c', 'c.id', '=', 'r.channel_id')
                ->where('r.tenant_id', Tenant::id())
                ->whereBetween('r.date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw('s.id, s.sku_code, s.name, c.code AS channel_code, c.name AS channel_name, c.color')
                ->selectRaw('COALESCE(SUM(r.net_sales),0) AS net_sales, COALESCE(SUM(r.units_sold),0) AS units')
                ->groupBy('s.id', 's.sku_code', 's.name', 'c.code', 'c.name', 'c.color')
                ->get();

            $channels = $rows->unique('channel_code')
                ->map(static fn (object $r): array => ['code' => $r->channel_code, 'name' => $r->channel_name, 'color' => $r->color])
                ->values();

            $matrix = $rows->groupBy('id')->map(static function ($group) use ($channels): array {
                $first = $group->first();

                return [
                    'sku_id' => $first->id,
                    'sku_code' => $first->sku_code,
                    'name' => $first->name,
                    'total' => (int) $group->sum('net_sales'),
                    ...$channels->mapWithKeys(static fn (array $channel): array => [
                        $channel['code'] => (int) ($group->firstWhere('channel_code', $channel['code'])->net_sales ?? 0),
                    ])->all(),
                ];
            })->sortByDesc('total')->take(30)->values();

            return ['rows' => $matrix->all(), 'channels' => $channels->all()];
        }), $filters);
    }

    public function orderStatus(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'order_status', $filters, function () use ($filters): array {
            $rows = DB::table('orders')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->when($filters->channelIds !== [], fn ($q) => $q->whereIn('channel_id', $filters->channelIds))
                ->selectRaw('status, COUNT(*) AS count, COALESCE(SUM(net_amount),0) AS net_sales')
                ->groupBy('status')
                ->get();

            $total = (int) $rows->sum('count');

            return ['rows' => $rows->map(static fn (object $r): array => [
                'status' => $r->status,
                'label' => OrderStatus::from($r->status)->label(),
                'count' => (int) $r->count,
                'net_sales' => (int) $r->net_sales,
                'share_pct' => Num::pct((int) $r->count, $total),
            ])->sortByDesc('count')->values()->all(), 'total' => $total];
        }), $filters);
    }

    public function paymentSplit(Request $request, PaymentModeQuery $payment): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'payment_split', $filters, fn (): array => $payment->handle($filters)), $filters);
    }

    public function topStates(Request $request, GeoQuery $geo): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'top_states', $filters, fn (): array => $geo->topStates($filters, 15)), $filters);
    }

    public function channelReturns(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'channel_returns', $filters, fn (): array => $returns->byChannel($filters)), $filters);
    }

    public function topReturnReasons(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            ['rows' => $this->cached('marketplace', 'return_reasons', $filters, fn (): array => $returns->byReason($filters, 12)->all())],
            $filters,
        );
    }

    public function zeroOrderSkus(Request $request, ProductQuery $products): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'zero_order_skus', $filters, fn (): array => $products->zeroOrderSkus($filters)), $filters);
    }

    public function fastMoving(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'fast_moving', $filters, function () use ($inventory, $filters): array {
            $rows = $inventory->coverage($filters)
                ->where('units_30d', '>', 0)
                ->sortByDesc('daily_rate')
                ->take(25)
                ->values();

            return ['rows' => $rows->all()];
        }), $filters);
    }

    public function inventoryValuation(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'valuation', $filters, fn (): array => $inventory->valuation($filters)), $filters);
    }

    public function recentOrders(Request $request, OrderQuery $orders): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($orders->recent($filters, min($request->integer('limit', 25), 100)), $filters);
    }

    public function orderRows(Request $request, OrderQuery $orders): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $orders->paginate(
                $filters,
                min($request->integer('per_page', 50), 200),
                $request->string('sort', 'placed_at')->toString(),
                $request->string('direction', 'desc')->toString(),
            ),
            $filters,
        );
    }

    /**
     * Settlement reconciliation: expected against received per cycle.
     */
    public function settlements(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'settlements', $filters, function () use ($filters): array {
            $rows = DB::table('settlements as s')
                ->leftJoin('channels as c', 'c.id', '=', 's.channel_id')
                ->where('s.tenant_id', Tenant::id())
                ->whereBetween('s.cycle_end', [$filters->period->fromDate(), $filters->period->toDate()])
                ->selectRaw('s.*, c.name AS channel_name, c.color')
                ->orderByDesc('s.cycle_end')
                ->get();

            if ($rows->isEmpty()) {
                return [
                    'rows' => [],
                    'caveat' => [
                        'message' => 'No settlement statements have been ingested yet. Settlement reconciliation needs a direct marketplace connector (Amazon SP-API, Flipkart Seller) — those ship in phase 2.',
                        'level' => 'warning',
                        'connector' => 'amazon_sp_api',
                    ],
                ];
            }

            $expected = (int) $rows->sum('expected_amount');
            $received = (int) $rows->sum('received_amount');

            return [
                'rows' => $rows->all(),
                'expected_total' => $expected,
                'received_total' => $received,
                'variance_total' => $received - $expected,
                'variance_pct' => Num::pct($received - $expected, max($expected, 1)),
            ];
        }), $filters);
    }

    /**
     * Buy Box / listing health needs a direct marketplace API. We say that
     * rather than showing a plausible-looking placeholder.
     */
    public function buybox(): JsonResponse
    {
        return ApiResponse::ok([
            'rows' => [],
            'caveat' => [
                'message' => 'Buy Box and listing health require a direct Amazon SP-API or Flipkart Seller connection. Unicommerce does not expose it, so this stays empty rather than showing an estimate.',
                'level' => 'warning',
                'connector' => 'amazon_sp_api',
            ],
        ]);
    }

    /**
     * Selling price against MRP across channels — where a marketplace is
     * quietly discounting you below your own store.
     */
    public function priceCompetitiveness(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('marketplace', 'price_competitiveness', $filters, function () use ($filters): array {
            $rows = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('skus as s', 's.id', '=', 'oi.sku_id')
                ->join('channels as c', 'c.id', '=', 'o.channel_id')
                ->where('oi.tenant_id', Tenant::id())
                ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->selectRaw('s.id, s.sku_code, s.name, s.mrp, c.code AS channel_code, c.name AS channel_name')
                ->selectRaw('AVG(oi.unit_price - (oi.discount / GREATEST(oi.qty, 1))) AS realised_price')
                ->selectRaw('COALESCE(SUM(oi.qty),0) AS units')
                ->groupBy('s.id', 's.sku_code', 's.name', 's.mrp', 'c.code', 'c.name')
                ->havingRaw('SUM(oi.qty) >= 3')
                ->get();

            $bySku = $rows->groupBy('id')->map(static function ($group): array {
                $first = $group->first();
                $prices = $group->map(static fn (object $r): array => [
                    'channel_code' => $r->channel_code,
                    'channel_name' => $r->channel_name,
                    'realised_price' => (int) round((float) $r->realised_price),
                    'units' => (int) $r->units,
                    'discount_from_mrp_pct' => Num::pct((int) $first->mrp - (int) round((float) $r->realised_price), (int) $first->mrp),
                ])->sortBy('realised_price')->values();

                return [
                    'sku_id' => $first->id,
                    'sku_code' => $first->sku_code,
                    'name' => $first->name,
                    'mrp' => (int) $first->mrp,
                    'channels' => $prices->all(),
                    'cheapest_channel' => $prices->first()['channel_name'] ?? null,
                    'price_spread' => (int) ($prices->last()['realised_price'] ?? 0) - (int) ($prices->first()['realised_price'] ?? 0),
                ];
            })->filter(static fn (array $row): bool => count($row['channels']) > 1)
                ->sortByDesc('price_spread')
                ->take(30)
                ->values();

            return [
                'rows' => $bySku->all(),
                'caveat' => 'Realised price is what customers actually paid after discounts, averaged over the window. SKUs sold on only one channel or fewer than 3 units are excluded.',
            ];
        }), $filters);
    }
}
