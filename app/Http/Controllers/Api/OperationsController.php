<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Operations\Queries\InventoryQuery;
use App\Domain\Operations\Queries\LogisticsQuery;
use App\Domain\Operations\Queries\ReturnsQuery;
use App\Domain\Sales\Queries\GeoQuery;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Num;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsController extends Controller
{
    use ResolvesFilters;

    public function kpis(Request $request, LogisticsQuery $logistics): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'kpis', $filters, fn (): array => $logistics->kpis($filters)), $filters);
    }

    public function returnsKpis(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);
        $threshold = (float) Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()])->return_threshold_pct;

        return ApiResponse::ok($this->cached('operations', 'returns_kpis', $filters, fn (): array => $returns->kpis($filters, $threshold)), $filters);
    }

    public function returnsByReason(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            ['rows' => $this->cached('operations', 'returns_by_reason', $filters, fn (): array => $returns->byReason($filters, 15)->all())],
            $filters,
        );
    }

    public function returnsByChannel(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'returns_by_channel', $filters, fn (): array => $returns->byChannel($filters)), $filters);
    }

    public function returnsTrend(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'returns_trend', $filters, fn (): array => $returns->trend($filters)), $filters);
    }

    public function shipmentStatus(Request $request, LogisticsQuery $logistics): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'shipment_status', $filters, fn (): array => $logistics->shipmentStatus($filters)), $filters);
    }

    public function courierScorecard(Request $request, LogisticsQuery $logistics): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'courier_scorecard', $filters, fn (): array => $logistics->courierScorecard($filters)), $filters);
    }

    public function rtoByState(Request $request, GeoQuery $geo): JsonResponse
    {
        $filters = $this->filters($request);
        $threshold = (float) Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()])->rto_threshold_pct;

        return ApiResponse::ok($this->cached('operations', 'rto_by_state', $filters, fn (): array => $geo->rtoByState($filters, $threshold, 40)), $filters);
    }

    public function deliveryFunnel(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'delivery_funnel', $filters, function () use ($filters): array {
            $row = DB::table('shipments as s')
                ->join('orders as o', 'o.id', '=', 's.order_id')
                ->where('s.tenant_id', Tenant::id())
                ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->when($filters->channelIds !== [], fn ($q) => $q->whereIn('o.channel_id', $filters->channelIds))
                ->selectRaw('COUNT(*) AS shipped')
                ->selectRaw("SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) AS delivered")
                ->selectRaw("SUM(CASE WHEN s.status IN ('manifested','in_transit','out_for_delivery','ndr') THEN 1 ELSE 0 END) AS in_transit")
                ->selectRaw('SUM(CASE WHEN s.is_rto = 1 THEN 1 ELSE 0 END) AS returned')
                ->first();

            $shipped = (int) ($row->shipped ?? 0);

            return [
                'steps' => [
                    ['key' => 'shipped', 'label' => 'Shipped', 'value' => $shipped, 'pct' => 100.0],
                    ['key' => 'delivered', 'label' => 'Delivered', 'value' => (int) ($row->delivered ?? 0), 'pct' => Num::pct((int) ($row->delivered ?? 0), $shipped)],
                    ['key' => 'in_transit', 'label' => 'In Transit', 'value' => (int) ($row->in_transit ?? 0), 'pct' => Num::pct((int) ($row->in_transit ?? 0), $shipped)],
                    ['key' => 'returned', 'label' => 'Returned', 'value' => (int) ($row->returned ?? 0), 'pct' => Num::pct((int) ($row->returned ?? 0), $shipped)],
                ],
            ];
        }), $filters);
    }

    public function deliveryPerformance(Request $request, LogisticsQuery $logistics): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'delivery_performance', $filters, fn (): array => $logistics->deliveryPerformance($filters)), $filters);
    }

    /** NDR is a live queue — caching it would hide the thing it exists to surface. */
    public function ndrQueue(Request $request, LogisticsQuery $logistics): JsonResponse
    {
        return ApiResponse::ok($logistics->ndrQueue($this->filters($request)), $this->filters($request));
    }

    /** Order aging deliberately ignores the date filter — it is a live board. */
    public function orderAging(LogisticsQuery $logistics): JsonResponse
    {
        return ApiResponse::ok($logistics->orderAging());
    }

    public function pincodeRisk(LogisticsQuery $logistics): JsonResponse
    {
        $rows = $logistics->pincodeRisk();

        return ApiResponse::ok([
            'rows' => $rows->all(),
            'count' => $rows->count(),
            'caveat' => 'Pincodes with fewer than 5 shipments are never scored — with that little history a risk score would be noise.',
        ]);
    }

    public function inventory(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'inventory', $filters, fn (): array => [
            'rows' => $inventory->coverage($filters)->values()->all(),
        ]), $filters);
    }

    public function reorder(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'reorder', $filters, fn (): array => $inventory->reorder($filters)), $filters);
    }

    public function stockouts(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'stockouts', $filters, fn (): array => $inventory->stockouts($filters)), $filters);
    }

    public function inventoryValuation(Request $request, InventoryQuery $inventory): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('operations', 'valuation', $filters, fn (): array => $inventory->valuation($filters)), $filters);
    }

    public function returnRows(Request $request, ReturnsQuery $returns): JsonResponse
    {
        $filters = $this->filters($request);
        $perPage = min($request->integer('per_page', 50), 200);

        $paginator = DB::table('returns as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('channels as c', 'c.id', '=', 'o.channel_id')
            ->leftJoin('skus as s', 's.id', '=', 'r.sku_id')
            ->where('r.tenant_id', Tenant::id())
            ->when(
                $filters->usesReturnDateBasis(),
                fn ($q) => $q->whereBetween('r.initiated_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')]),
                fn ($q) => $q->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')]),
            )
            ->when($filters->channelIds !== [], fn ($q) => $q->whereIn('o.channel_id', $filters->channelIds))
            ->selectRaw('r.id, r.type, r.reason_code, r.reason_text, r.qty, r.initiated_at, r.received_at')
            ->selectRaw('r.refund_amount, r.loss_amount, r.restock, r.shipping_state')
            ->selectRaw('o.order_number, o.placed_at, o.payment_mode, o.net_amount, c.name AS channel_name')
            ->selectRaw('s.sku_code, s.name AS sku_name, s.hsn, s.gst_rate')
            ->orderByDesc('r.initiated_at')
            ->paginate($perPage);

        return ApiResponse::ok($paginator, $filters, [
            'basis' => $filters->returnsBasis,
            'columns_note' => 'Column order matches the Tally returns register import format.',
        ]);
    }
}
