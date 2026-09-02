<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Sales\Queries\OrderQuery;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Support\Facades\Tenant;
use App\Support\Num;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Row-level truth behind any number on any screen.
 *
 * Every widget can hand this a dimension and a value — "state = Bihar",
 * "sku = KL-102", "channel = Amazon" — and get back the actual orders, so a
 * figure a founder disbelieves can always be opened up.
 */
class DrilldownController extends Controller
{
    use ResolvesFilters;

    /** Only these dimensions can be drilled; anything else is refused. */
    private const DIMENSIONS = [
        'channel' => ['column' => 'o.channel_id', 'label' => 'Channel', 'numeric' => true],
        'state' => ['column' => 'o.shipping_state', 'label' => 'State'],
        'city' => ['column' => 'o.shipping_city', 'label' => 'City'],
        'payment_mode' => ['column' => 'o.payment_mode', 'label' => 'Payment mode'],
        'status' => ['column' => 'o.status', 'label' => 'Status'],
        'discount_code' => ['column' => 'o.discount_codes', 'label' => 'Discount code'],
        'courier' => ['column' => 's.courier', 'label' => 'Courier', 'join' => 'shipments'],
        'sku' => ['column' => 'oi.sku_code', 'label' => 'SKU', 'join' => 'items'],
        'customer' => ['column' => 'o.customer_id', 'label' => 'Customer', 'numeric' => true],
    ];

    public function dimensions(): JsonResponse
    {
        return ApiResponse::ok([
            'dimensions' => collect(self::DIMENSIONS)
                ->map(static fn (array $meta, string $key): array => ['key' => $key, 'label' => $meta['label']])
                ->values()
                ->all(),
        ]);
    }

    public function orders(Request $request, OrderQuery $orders): JsonResponse
    {
        $validated = $request->validate([
            'dimension' => ['nullable', 'string'],
            'value' => ['nullable', 'string', 'max:190'],
            'only' => ['nullable', 'in:loss,returned,rto,cod,unshipped'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $dimension = $validated['dimension'] ?? null;

        if ($dimension !== null && ! array_key_exists($dimension, self::DIMENSIONS)) {
            return ApiResponse::error("Cannot drill down by [{$dimension}].", 422);
        }

        $filters = $this->filters($request);
        $limit = (int) ($validated['limit'] ?? 200);

        $query = DB::table('orders as o')
            ->leftJoin('channels as c', 'c.id', '=', 'o.channel_id')
            ->where('o.tenant_id', Tenant::id())
            ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')]);

        if ($filters->channelIds !== []) {
            $query->whereIn('o.channel_id', $filters->channelIds);
        }

        if ($filters->paymentMode !== null) {
            $query->where('o.payment_mode', $filters->paymentMode);
        }

        if ($dimension !== null) {
            $this->applyDimension($query, $dimension, $validated['value'] ?? null);
        }

        match ($validated['only'] ?? null) {
            'loss' => $query->where('o.contribution_margin', '<', 0),
            'returned' => $query->where('o.status', 'returned'),
            'rto' => $query->where('o.status', 'rto'),
            'cod' => $query->where('o.payment_mode', 'cod'),
            'unshipped' => $query->whereIn('o.status', ['placed', 'confirmed']),
            default => null,
        };

        $total = (clone $query)->distinct()->count('o.id');

        $rows = $query
            ->selectRaw('o.id, o.order_number, o.placed_at, o.status, o.payment_mode, o.shipping_state, o.shipping_city')
            ->selectRaw('o.units_count, o.gross_amount, o.discount_amount, o.net_amount, o.cogs_amount, o.fees_amount, o.logistics_amount, o.contribution_margin')
            ->selectRaw('c.name AS channel')
            ->groupBy('o.id', 'o.order_number', 'o.placed_at', 'o.status', 'o.payment_mode', 'o.shipping_state',
                'o.shipping_city', 'o.units_count', 'o.gross_amount', 'o.discount_amount', 'o.net_amount',
                'o.cogs_amount', 'o.fees_amount', 'o.logistics_amount', 'o.contribution_margin', 'c.name')
            ->orderByDesc('o.placed_at')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): array => [
                ...(array) $row,
                'margin_pct' => Num::pct((int) $row->contribution_margin, (int) $row->net_amount),
            ]);

        return ApiResponse::ok([
            'rows' => $rows->all(),
            'total' => $total,
            'shown' => $rows->count(),
            'truncated' => $total > $rows->count(),
            'dimension' => $dimension,
            'value' => $validated['value'] ?? null,
            'totals' => [
                'net_sales' => (int) $rows->sum('net_amount'),
                'contribution_margin' => (int) $rows->sum('contribution_margin'),
            ],
        ], $filters);
    }

    private function applyDimension(Builder $query, string $dimension, ?string $value): void
    {
        $meta = self::DIMENSIONS[$dimension];

        if (($meta['join'] ?? null) === 'items') {
            $query->join('order_items as oi', 'oi.order_id', '=', 'o.id');
        }

        if (($meta['join'] ?? null) === 'shipments') {
            $query->join('shipments as s', 's.order_id', '=', 'o.id');
        }

        if ($value === null || $value === '') {
            $query->whereNull($meta['column']);

            return;
        }

        // Channels and customers are addressed by name in the UI but stored by
        // id, so resolve the name here rather than making the caller do it.
        if ($dimension === 'channel' && ! ctype_digit($value)) {
            $query->where('c.name', $value);

            return;
        }

        $query->where($meta['column'], ($meta['numeric'] ?? false) ? (int) $value : $value);
    }

    /**
     * A single order, in full, for the deepest level of the drill-down.
     */
    public function order(int $order): JsonResponse
    {
        $model = Order::query()->with(['items', 'channel', 'customer', 'shipments'])->find($order);

        if ($model === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::ok([
            'order' => [
                'id' => $model->id,
                'order_number' => $model->order_number,
                'placed_at' => $model->placed_at?->toIso8601String(),
                'status' => $model->status->label(),
                'payment_mode' => $model->payment_mode->label(),
                'channel' => $model->channel?->name,
                'customer' => $model->customer?->name,
                'state' => $model->shipping_state,
                'city' => $model->shipping_city,
                'gross_amount' => $model->gross_amount,
                'discount_amount' => $model->discount_amount,
                'net_amount' => $model->net_amount,
                'cogs_amount' => $model->cogs_amount,
                'fees_amount' => $model->fees_amount,
                'logistics_amount' => $model->logistics_amount,
                'contribution_margin' => $model->contribution_margin,
            ],
            'items' => $model->items->map(static fn ($item): array => [
                'sku_code' => $item->sku_code,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'line_net' => $item->line_net,
                'cogs_unit' => $item->cogs_unit,
            ])->all(),
            'shipments' => $model->shipments->map(static fn ($shipment): array => [
                'awb' => $shipment->awb,
                'courier' => $shipment->courier,
                'status' => $shipment->status->value,
                'attempts' => $shipment->attempts,
                'transit_days' => $shipment->transit_days,
            ])->all(),
        ]);
    }
}
