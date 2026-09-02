<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Customers\Queries\CohortQuery;
use App\Domain\Operations\Queries\InventoryQuery;
use App\Domain\Operations\Queries\ReturnsQuery;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Domain\Sales\Queries\ChannelQuery;
use App\Domain\Sales\Queries\GeoQuery;
use App\Domain\Sales\Queries\HealthFlagsQuery;
use App\Domain\Sales\Queries\KpiQuery;
use App\Domain\Sales\Queries\OrderQuery;
use App\Domain\Sales\Queries\PacingQuery;
use App\Domain\Sales\Queries\PaymentModeQuery;
use App\Domain\Sales\Queries\ProductQuery;
use App\Domain\Sales\Queries\SalesSummaryQuery;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Num;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesFilters;

    public function kpis(Request $request, KpiQuery $kpis): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'kpis', $filters, fn (): array => $kpis->dashboard($filters)),
            $filters,
        );
    }

    public function salesByChannelDaily(Request $request, ChannelQuery $channels): JsonResponse
    {
        $filters = $this->filters($request);
        $column = $request->string('metric', 'invoiced_sales')->toString();
        $column = in_array($column, ['invoiced_sales', 'net_sales', 'gross_sales', 'orders_count'], true) ? $column : 'invoiced_sales';

        return ApiResponse::ok(
            $this->cached('dashboard', 'sales_by_channel:'.$column, $filters, fn (): array => $channels->daily($filters, $column)),
            $filters,
        );
    }

    public function salesSummary(Request $request, SalesSummaryQuery $summary): JsonResponse
    {
        $filters = $this->filters($request);
        $data = $this->cached('dashboard', 'sales_summary', $filters, fn (): array => $summary->handle($filters));

        return ApiResponse::ok($data, $filters);
    }

    public function salesJourney(Request $request, SalesSummaryQuery $summary): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'sales_journey', $filters, fn (): array => $summary->journey($filters)),
            $filters,
        );
    }

    public function revenueMarginTrend(Request $request, RollupQuery $rollups): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'revenue_margin_trend', $filters, fn (): array => $rollups
                ->daily($filters, ['net_sales', 'contribution_margin'])
                ->map(static fn (array $row): array => [
                    'date' => $row['date'],
                    'net_sales' => $row['net_sales'],
                    'contribution_margin' => $row['contribution_margin'],
                    'margin_pct' => Num::pct($row['contribution_margin'], $row['net_sales']),
                ])->all()),
            $filters,
        );
    }

    public function paymentModeEconomics(Request $request, PaymentModeQuery $query): JsonResponse
    {
        $filters = $this->filters($request);
        $data = $this->cached('dashboard', 'payment_mode', $filters, fn (): array => $query->handle($filters));

        return ApiResponse::ok($data, $filters);
    }

    public function channelMix(Request $request, ChannelQuery $channels): JsonResponse
    {
        $filters = $this->filters($request);
        $data = $this->cached('dashboard', 'channel_mix', $filters, fn (): array => $channels->mix($filters));

        return ApiResponse::ok($data, $filters);
    }

    public function topStates(Request $request, GeoQuery $geo): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'top_states', $filters, fn (): array => $geo->topStates($filters)),
            $filters,
        );
    }

    public function topRtoStates(Request $request, GeoQuery $geo): JsonResponse
    {
        $filters = $this->filters($request);
        $threshold = (float) Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()])->rto_threshold_pct;

        return ApiResponse::ok(
            $this->cached('dashboard', 'top_rto_states', $filters, fn (): array => $geo->rtoByState($filters, $threshold)),
            $filters,
        );
    }

    public function stateActionMatrix(Request $request, GeoQuery $geo): JsonResponse
    {
        $filters = $this->filters($request);
        $threshold = (float) Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()])->rto_threshold_pct;

        return ApiResponse::ok(
            $this->cached('dashboard', 'state_action_matrix', $filters, fn (): array => $geo->actionMatrix($filters, $threshold)),
            $filters,
        );
    }

    public function topCategories(Request $request, ProductQuery $products): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'top_categories', $filters, fn (): array => $products->topCategories($filters)),
            $filters,
        );
    }

    public function skuPareto(Request $request, ProductQuery $products): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'sku_pareto', $filters, fn (): array => $products->pareto($filters)),
            $filters,
        );
    }

    public function returnsByChannel(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'returns_by_channel', $filters, fn (): array => $returns->byChannel($filters)),
            $filters,
        );
    }

    public function topReturnReasons(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            ['rows' => $this->cached('dashboard', 'top_return_reasons', $filters, fn (): array => $returns->byReason($filters)->all())],
            $filters,
        );
    }

    public function highReturnProducts(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            ['rows' => $this->cached('dashboard', 'top_return_skus', $filters, fn (): array => $returns->topReturnSkus($filters)->all())],
            $filters,
        );
    }

    public function topLossOrders(Request $request, OrderQuery $orders): JsonResponse
    {
        $filters = $this->filters($request);
        $data = $this->cached('dashboard', 'loss_orders', $filters, fn (): array => $orders->lossMaking($filters));

        return ApiResponse::ok($data, $filters);
    }

    public function recentOrders(Request $request, OrderQuery $orders): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($orders->recent($filters, $request->integer('limit', 10)), $filters);
    }

    public function healthFlags(Request $request, HealthFlagsQuery $flags): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            ['rows' => $this->cached('dashboard', 'health_flags', $filters, fn (): array => $flags->handle($filters))],
            $filters,
        );
    }

    public function revenuePacing(Request $request, PacingQuery $pacing): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'revenue_pacing', $filters, fn (): array => $pacing->handle($filters)),
            $filters,
        );
    }

    public function criticalInventory(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('dashboard', 'critical_inventory', $filters, fn (): array => $inventory->critical($filters)),
            $filters,
        );
    }

    public function ltvCohort(Request $request, CohortQuery $cohorts): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('dashboard', 'ltv_cohort', $filters, fn (): array => $cohorts->summary()), $filters);
    }
}
