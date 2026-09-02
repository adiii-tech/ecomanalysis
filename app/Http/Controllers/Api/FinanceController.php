<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Reports\Queries\PnlQuery;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Domain\Sales\Queries\GeoQuery;
use App\Domain\Sales\Queries\KpiQuery;
use App\Domain\Sales\Queries\ProductQuery;
use App\Domain\Sales\Queries\SalesSummaryQuery;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Facades\Tenant;
use App\Support\Num;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    use ResolvesFilters;

    public function kpis(Request $request, KpiQuery $kpis): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'kpis', $filters, fn (): array => $kpis->finance($filters)), $filters);
    }

    public function salesOverTime(Request $request, RollupQuery $rollups): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'sales_over_time', $filters, function () use ($rollups, $filters): array {
            $current = $rollups->daily($filters, ['net_sales', 'invoiced_sales', 'orders_count']);
            $previous = $rollups->daily($filters->previous(), ['net_sales'])->values();

            return $current->values()->map(static fn (array $row, int $index): array => [
                'date' => $row['date'],
                'net_sales' => $row['net_sales'],
                'invoiced_sales' => $row['invoiced_sales'],
                'orders' => $row['orders_count'],
                'prev_net_sales' => $previous[$index]['net_sales'] ?? 0,
            ])->all();
        }), $filters);
    }

    public function aovTrend(Request $request, RollupQuery $rollups): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'aov_trend', $filters, function () use ($rollups, $filters): array {
            $current = $rollups->daily($filters, ['net_sales', 'orders_count']);
            $previous = $rollups->daily($filters->previous(), ['net_sales', 'orders_count'])->values();

            return $current->values()->map(static fn (array $row, int $index): array => [
                'date' => $row['date'],
                'aov' => (int) round(Num::safeDivide($row['net_sales'], $row['orders_count'])),
                'prev_aov' => (int) round(Num::safeDivide($previous[$index]['net_sales'] ?? 0, $previous[$index]['orders_count'] ?? 0)),
            ])->all();
        }), $filters);
    }

    public function revenueBreakdown(Request $request, SalesSummaryQuery $summary): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            ['steps' => $this->cached('finance', 'revenue_breakdown', $filters, fn (): array => $summary->waterfall($filters))],
            $filters,
        );
    }

    public function geographicSales(Request $request, GeoQuery $geo): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'geo', $filters, fn (): array => $geo->topStates($filters, 40)), $filters);
    }

    public function topSkus(Request $request, ProductQuery $products): JsonResponse
    {
        $filters = $this->filters($request);
        $limit = min($request->integer('limit', 20), 200);

        return ApiResponse::ok(
            ['rows' => $this->cached('finance', 'top_skus:'.$limit, $filters, fn (): array => $products->topSkus($filters, $limit)->all())],
            $filters,
        );
    }

    public function paymentMethodSplit(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'payment_split', $filters, function () use ($filters): array {
            $rows = DB::table('transactions')
                ->where('tenant_id', Tenant::id())
                ->where('kind', 'sale')
                ->where('status', 'success')
                ->whereBetween('processed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->selectRaw("COALESCE(NULLIF(method, ''), gateway, 'Unknown') AS method")
                ->selectRaw('COUNT(*) AS transactions, COALESCE(SUM(amount),0) AS amount, COALESCE(SUM(fee),0) AS fee')
                ->groupByRaw("COALESCE(NULLIF(method, ''), gateway, 'Unknown')")
                ->orderByDesc('amount')
                ->get();

            $codOrders = (int) DB::table('daily_metrics_rollup')
                ->where('tenant_id', Tenant::id())
                ->where('payment_mode', 'cod')
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->sum('orders_count');

            $codAmount = (int) DB::table('daily_metrics_rollup')
                ->where('tenant_id', Tenant::id())
                ->where('payment_mode', 'cod')
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->sum('net_sales');

            $total = (int) $rows->sum('amount') + $codAmount;

            $methods = $rows->map(static fn (object $row): array => [
                'method' => $row->method,
                'transactions' => (int) $row->transactions,
                'amount' => (int) $row->amount,
                'fee' => (int) $row->fee,
                'share_pct' => 0.0,
            ])->push([
                'method' => 'Cash on Delivery',
                'transactions' => $codOrders,
                'amount' => $codAmount,
                'fee' => 0,
                'share_pct' => 0.0,
            ]);

            return [
                'rows' => $methods->map(static fn (array $row): array => [...$row, 'share_pct' => Num::pct($row['amount'], $total)])
                    ->sortByDesc('amount')->values()->all(),
                'total' => $total,
            ];
        }), $filters);
    }

    public function refunds(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'refunds', $filters, function () use ($filters): array {
            $rows = DB::table('transactions as t')
                ->leftJoin('orders as o', 'o.id', '=', 't.order_id')
                ->where('t.tenant_id', Tenant::id())
                ->where('t.kind', 'refund')
                ->whereBetween('t.processed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->selectRaw('t.id, t.gateway, t.method, t.amount, t.processed_at, t.status, o.order_number, o.shipping_state')
                ->orderByDesc('t.processed_at')
                ->limit(500)
                ->get();

            return [
                'rows' => $rows->all(),
                'count' => $rows->count(),
                'total' => abs((int) $rows->sum('amount')),
            ];
        }), $filters);
    }

    public function transactions(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $perPage = min($request->integer('per_page', 50), 200);

        $paginator = DB::table('transactions as t')
            ->leftJoin('orders as o', 'o.id', '=', 't.order_id')
            ->where('t.tenant_id', Tenant::id())
            ->whereBetween('t.processed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->when($request->filled('kind'), fn ($q) => $q->where('t.kind', $request->string('kind')))
            ->when($request->filled('status'), fn ($q) => $q->where('t.status', $request->string('status')))
            ->when($filters->search !== null, fn ($q) => $q->where('o.order_number', 'like', '%'.$filters->search.'%'))
            ->selectRaw('t.id, t.gateway, t.method, t.kind, t.amount, t.fee, t.status, t.failure_reason, t.processed_at')
            ->selectRaw('o.order_number, o.payment_mode, o.shipping_state')
            ->orderByDesc('t.processed_at')
            ->paginate($perPage);

        return ApiResponse::ok($paginator, $filters);
    }

    public function pnl(Request $request, PnlQuery $pnl): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'pnl', $filters, fn (): array => $pnl->handle($filters)), $filters);
    }

    /**
     * Money in vs money stuck: COD awaiting remittance and the refund liability
     * sitting behind open returns.
     */
    public function cashFlow(Request $request, RollupQuery $rollups): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'cash_flow', $filters, function () use ($filters, $rollups): array {
            $totals = $rollups->totals($filters);

            $cod = DB::table('shipments as s')
                ->join('orders as o', 'o.id', '=', 's.order_id')
                ->where('s.tenant_id', Tenant::id())
                ->where('s.payment_mode', 'cod')
                ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->selectRaw('COALESCE(SUM(CASE WHEN s.cod_collected_at IS NOT NULL AND s.cod_remitted_at IS NULL THEN o.net_amount ELSE 0 END),0) AS awaiting_remittance')
                ->selectRaw('COALESCE(SUM(CASE WHEN s.cod_remitted_at IS NOT NULL THEN o.net_amount ELSE 0 END),0) AS remitted')
                ->selectRaw('COALESCE(SUM(CASE WHEN s.cod_collected_at IS NULL AND s.is_rto = 0 THEN o.net_amount ELSE 0 END),0) AS in_transit')
                ->selectRaw('AVG(CASE WHEN s.cod_remitted_at IS NOT NULL THEN DATEDIFF(s.cod_remitted_at, s.cod_collected_at) END) AS avg_remit_days')
                ->first();

            $openReturns = (int) DB::table('returns')
                ->where('tenant_id', Tenant::id())
                ->whereNull('received_at')
                ->sum('refund_amount');

            $prepaidIn = (int) DB::table('daily_metrics_rollup')
                ->where('tenant_id', Tenant::id())
                ->where('payment_mode', 'prepaid')
                ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
                ->sum('net_sales');

            return [
                'money_in' => $prepaidIn + (int) $cod->remitted,
                'prepaid_settled' => $prepaidIn,
                'cod_remitted' => (int) $cod->remitted,
                'cod_awaiting_remittance' => (int) $cod->awaiting_remittance,
                'cod_in_transit' => (int) $cod->in_transit,
                'avg_remittance_days' => round((float) ($cod->avg_remit_days ?? 0), 1),
                'returns_liability' => $openReturns,
                'working_capital_locked' => (int) $cod->awaiting_remittance + (int) $cod->in_transit + $openReturns,
                'net_sales' => $totals['net_sales'],
                'caveat' => 'COD remittance timing comes from the courier feed. Anything not yet scanned as collected shows as in transit.',
            ];
        }), $filters);
    }

    /**
     * Output tax by HSN, derived from the GST rate stored on each SKU.
     */
    public function gst(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('finance', 'gst', $filters, function () use ($filters): array {
            $rows = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('skus as s', 's.id', '=', 'oi.sku_id')
                ->where('oi.tenant_id', Tenant::id())
                ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->where('o.status', '!=', 'cancelled')
                ->selectRaw("COALESCE(NULLIF(s.hsn, ''), 'Unclassified') AS hsn, s.gst_rate")
                ->selectRaw('COUNT(DISTINCT o.id) AS orders, COALESCE(SUM(oi.qty),0) AS units')
                ->selectRaw('COALESCE(SUM(oi.line_net),0) AS taxable_value')
                ->selectRaw('COALESCE(SUM(oi.tax),0) AS tax_amount')
                ->groupBy('hsn', 's.gst_rate')
                ->orderByDesc('taxable_value')
                ->get();

            return [
                'rows' => $rows->map(static fn (object $row): array => [
                    'hsn' => $row->hsn,
                    'gst_rate' => (float) $row->gst_rate,
                    'orders' => (int) $row->orders,
                    'units' => (int) $row->units,
                    'taxable_value' => (int) $row->taxable_value - (int) $row->tax_amount,
                    'tax_amount' => (int) $row->tax_amount,
                    'cgst' => (int) round((int) $row->tax_amount / 2),
                    'sgst' => (int) round((int) $row->tax_amount / 2),
                    'total_value' => (int) $row->taxable_value,
                ])->all(),
                'total_tax' => (int) $rows->sum('tax_amount'),
                'total_taxable' => (int) $rows->sum('taxable_value') - (int) $rows->sum('tax_amount'),
                'caveat' => 'Tax is computed from the GST rate on each SKU under an inclusive-pricing assumption, and split CGST/SGST evenly. It is a working view, not a filed return — reconcile against your books before filing.',
            ];
        }), $filters);
    }
}
