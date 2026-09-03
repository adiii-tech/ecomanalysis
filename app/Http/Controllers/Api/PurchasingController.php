<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Queries\StockQuery;
use App\Domain\Inventory\Services\CostAverager;
use App\Domain\Inventory\Services\StockLedger;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Verdict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Suppliers and purchase orders — the "how stock gets here" half of inventory.
 */
class PurchasingController extends Controller
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly CostAverager $costs,
    ) {}

    public function suppliers(): JsonResponse
    {
        $rows = Supplier::query()
            ->withCount(['purchaseOrders', 'skus'])
            ->orderBy('name')
            ->get()
            ->map(static fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'gstin' => $supplier->gstin,
                'city' => $supplier->city,
                'state' => $supplier->state,
                'lead_time_days' => $supplier->lead_time_days,
                'payment_terms_days' => $supplier->payment_terms_days,
                'is_active' => $supplier->is_active,
                'purchase_orders' => (int) $supplier->getAttribute('purchase_orders_count'),
                'skus' => (int) $supplier->getAttribute('skus_count'),
            ]);

        return ApiResponse::ok(['rows' => $rows->all()]);
    }

    public function saveSupplier(Request $request, ?int $supplier = null): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model = $supplier !== null ? Supplier::query()->find($supplier) : null;

        if ($supplier !== null && $model === null) {
            return ApiResponse::error('Supplier not found.', 404);
        }

        $model ??= new Supplier(['tenant_id' => Tenant::id()]);
        $model->forceFill($validated)->save();

        return ApiResponse::ok(['id' => $model->id], message: 'Supplier saved.');
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();

        $rows = PurchaseOrder::query()
            ->with(['supplier:id,name', 'location:id,name'])
            ->withCount('items')
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(static fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'status' => $order->status,
                'supplier' => $order->supplier?->name,
                'location' => $order->location?->name,
                'expected_at' => $order->expected_at?->toDateString(),
                'subtotal' => $order->subtotal,
                'total' => $order->total,
                'items' => (int) $order->getAttribute('items_count'),
                'is_overdue' => $order->expected_at !== null
                    && $order->expected_at->isPast()
                    && in_array($order->status, ['sent', 'partial'], true),
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        $open = $rows->whereIn('status', ['draft', 'sent', 'partial']);

        return ApiResponse::ok([
            'rows' => $rows->all(),
            'summary' => [
                'open' => $open->count(),
                'open_value' => (int) $open->sum('total'),
                'overdue' => $rows->where('is_overdue', true)->count(),
            ],
            'verdict' => $this->verdict($rows->all())->toArray(),
        ]);
    }

    public function purchaseOrder(int $order): JsonResponse
    {
        $model = PurchaseOrder::query()
            ->with(['supplier', 'location:id,name', 'items.sku:id,sku_code,name,cost_price'])
            ->find($order);

        if ($model === null) {
            return ApiResponse::error('Purchase order not found.', 404);
        }

        return ApiResponse::ok([
            'order' => [
                'id' => $model->id,
                'po_number' => $model->po_number,
                'status' => $model->status,
                'supplier_id' => $model->supplier_id,
                'supplier' => $model->supplier?->name,
                'location_id' => $model->location_id,
                'location' => $model->location?->name,
                'expected_at' => $model->expected_at?->toDateString(),
                'freight_cost' => $model->freight_cost,
                'other_cost' => $model->other_cost,
                'subtotal' => $model->subtotal,
                'tax_amount' => $model->tax_amount,
                'total' => $model->total,
                'notes' => $model->notes,
                'is_editable' => $model->isEditable(),
                'is_receivable' => $model->isReceivable(),
            ],
            'items' => $model->items->map(static fn (PurchaseOrderItem $item): array => [
                'id' => $item->id,
                'sku_id' => $item->sku_id,
                'sku_code' => $item->sku?->sku_code,
                'name' => $item->sku?->name,
                'quantity_ordered' => $item->quantity_ordered,
                'quantity_received' => $item->quantity_received,
                'outstanding' => $item->quantityOutstanding(),
                'unit_cost' => $item->unit_cost,
                'landed_unit_cost' => $item->landed_unit_cost,
                'current_cost' => (int) ($item->sku?->cost_price ?? 0),
                'line_total' => $item->line_total,
            ])->all(),
        ]);
    }

    public function savePurchaseOrder(Request $request, ?int $order = null): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'expected_at' => ['nullable', 'date'],
            'freight_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.sku_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $model = $order !== null ? PurchaseOrder::query()->find($order) : null;

        if ($order !== null && $model === null) {
            return ApiResponse::error('Purchase order not found.', 404);
        }

        if ($model !== null && ! $model->isEditable()) {
            return ApiResponse::error('This order has already gone to the supplier and can no longer be edited.', 422);
        }

        $freight = Money::fromRupees((float) ($validated['freight_cost'] ?? 0));
        $other = Money::fromRupees((float) ($validated['other_cost'] ?? 0));

        $lines = array_map(static fn (array $line): array => [
            'sku_id' => (int) $line['sku_id'],
            'quantity' => (int) $line['quantity'],
            'unit_cost' => Money::fromRupees((float) $line['unit_cost']),
            'tax_rate' => (float) ($line['tax_rate'] ?? 0),
        ], $validated['items']);

        $landed = $this->costs->allocateLandedCost($lines, $freight + $other);

        $saved = DB::transaction(function () use ($model, $validated, $lines, $landed, $freight, $other): PurchaseOrder {
            $subtotal = 0;
            $tax = 0;

            foreach ($lines as $index => $line) {
                $lineTotal = $line['quantity'] * $line['unit_cost'];
                $subtotal += $lineTotal;
                $tax += (int) round($lineTotal * $line['tax_rate'] / 100);
            }

            $po = $model ?? new PurchaseOrder([
                'tenant_id' => Tenant::id(),
                'created_by' => request()->user()?->id,
                'po_number' => $this->nextPoNumber(),
                'status' => 'draft',
            ]);

            $po->forceFill([
                'supplier_id' => $validated['supplier_id'] ?? null,
                'location_id' => $validated['location_id'] ?? $this->ledger->defaultLocationId(),
                'expected_at' => $validated['expected_at'] ?? null,
                'freight_cost' => $freight,
                'other_cost' => $other,
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $subtotal + $tax + $freight + $other,
                'notes' => $validated['notes'] ?? null,
            ])->save();

            $po->items()->delete();

            foreach ($lines as $index => $line) {
                PurchaseOrderItem::query()->create([
                    'tenant_id' => Tenant::id(),
                    'purchase_order_id' => $po->id,
                    'sku_id' => $line['sku_id'],
                    'quantity_ordered' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'tax_rate' => $line['tax_rate'],
                    'line_total' => $line['quantity'] * $line['unit_cost'],
                    'landed_unit_cost' => $landed[$index] ?? $line['unit_cost'],
                ]);
            }

            return $po;
        });

        activity('inventory')->performedOn($saved)
            ->withProperties(['po' => $saved->po_number, 'lines' => count($lines), 'total' => $saved->total])
            ->log('purchase_order.saved');

        return ApiResponse::ok([
            'id' => $saved->id,
            'po_number' => $saved->po_number,
            'total' => $saved->total,
        ], message: sprintf('%s saved — %s including freight.', $saved->po_number, Money::format($saved->total)));
    }

    public function sendPurchaseOrder(int $order): JsonResponse
    {
        $model = PurchaseOrder::query()->find($order);

        if ($model === null) {
            return ApiResponse::error('Purchase order not found.', 404);
        }

        if (! $model->isEditable()) {
            return ApiResponse::error('Only a draft can be sent.', 422);
        }

        $model->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

        // Incoming is what a reorder decision should net off against.
        foreach ($model->items as $item) {
            $this->bumpIncoming($item->sku_id, $model->location_id, $item->quantityOutstanding());
        }

        activity('inventory')->performedOn($model)->log('purchase_order.sent');

        return ApiResponse::ok(null, message: sprintf('%s marked as sent. Its quantities now show as incoming.', $model->po_number));
    }

    public function receivePurchaseOrder(Request $request, int $order, ReceivePurchaseOrder $receive): JsonResponse
    {
        $model = PurchaseOrder::query()->with('items.sku')->find($order);

        if ($model === null) {
            return ApiResponse::error('Purchase order not found.', 404);
        }

        $validated = $request->validate([
            'received' => ['required', 'array', 'min:1'],
            'received.*' => ['integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $receive->handle($model, $validated['received'], $validated['note'] ?? null);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        foreach ($model->items()->get() as $item) {
            $this->setIncoming($item->sku_id, $model->location_id, $item->quantityOutstanding());
        }

        activity('inventory')->performedOn($model)->withProperties($result)->log('purchase_order.received');

        return ApiResponse::ok($result, message: sprintf(
            '%d units booked in across %d lines. Costs re-averaged at the landed price.',
            $result['received'], $result['lines'],
        ));
    }

    public function cancelPurchaseOrder(int $order): JsonResponse
    {
        $model = PurchaseOrder::query()->find($order);

        if ($model === null) {
            return ApiResponse::error('Purchase order not found.', 404);
        }

        if ($model->status === 'received') {
            return ApiResponse::error('This order was fully received; it cannot be cancelled.', 422);
        }

        $model->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

        foreach ($model->items as $item) {
            $this->setIncoming($item->sku_id, $model->location_id, 0);
        }

        return ApiResponse::ok(null, message: sprintf('%s cancelled.', $model->po_number));
    }

    /**
     * A suggested order built from what is actually below its reorder point,
     * grouped by supplier so it can be sent as-is.
     */
    public function suggestions(): JsonResponse
    {
        $rows = collect(app(StockQuery::class)->levels(null, null, 'reorder')['rows'])
            ->map(static function (array $row): array {
                $suggested = $row['reorder_quantity']
                    ?? max(1, (int) ceil(($row['daily_rate'] * max(7, (int) ($row['lead_time_days'] ?? 7)) * 2) - $row['on_hand']));

                return [
                    ...$row,
                    'suggested_quantity' => $suggested,
                    'suggested_cost' => $suggested * $row['cost_price'],
                ];
            })
            ->groupBy('supplier_name')
            ->map(static fn ($group, $supplier): array => [
                'supplier' => $supplier ?: 'No supplier set',
                'skus' => $group->count(),
                'units' => (int) $group->sum('suggested_quantity'),
                'cost' => (int) $group->sum('suggested_cost'),
                'rows' => $group->values()->all(),
            ])
            ->values();

        return ApiResponse::ok([
            'groups' => $rows->all(),
            'total_cost' => (int) $rows->sum('cost'),
            'caveat' => 'Suggested quantities cover the lead time twice over at the last 30 days of sell-through. Seasonality is not modelled — treat it as a starting point.',
        ]);
    }

    private function nextPoNumber(): string
    {
        $count = PurchaseOrder::query()->count() + 1;

        return 'PO-'.now()->format('ymd').'-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    private function bumpIncoming(int $skuId, ?int $locationId, int $quantity): void
    {
        Inventory::query()->updateOrCreate(
            ['tenant_id' => Tenant::id(), 'sku_id' => $skuId, 'location_id' => $locationId, 'source' => StockLedger::SOURCE],
            ['incoming' => DB::raw('incoming + '.$quantity)],
        );
    }

    private function setIncoming(int $skuId, ?int $locationId, int $quantity): void
    {
        Inventory::query()
            ->where('sku_id', $skuId)
            ->where('location_id', $locationId)
            ->where('source', StockLedger::SOURCE)
            ->update(['incoming' => max(0, $quantity)]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function verdict(array $rows): Verdict
    {
        $overdue = array_values(array_filter($rows, static fn (array $row): bool => $row['is_overdue']));

        if ($overdue !== []) {
            $value = array_sum(array_map(static fn (array $row): int => (int) $row['total'], $overdue));

            return Verdict::bad(
                sprintf('%d purchase orders are past their expected date', count($overdue)),
                sprintf('%s of stock you are counting on has not arrived.', Money::format($value)),
                'Chase the supplier, or move the date so your cover figures stop lying to you.',
                $value,
            );
        }

        $open = array_values(array_filter($rows, static fn (array $row): bool => in_array($row['status'], ['draft', 'sent', 'partial'], true)));

        if ($open === []) {
            return Verdict::neutral('No open purchase orders', 'Nothing is on its way in right now.');
        }

        return Verdict::good(
            sprintf('%d open orders, all on schedule', count($open)),
            sprintf('%s of stock inbound.', Money::format(array_sum(array_map(static fn (array $row): int => (int) $row['total'], $open)))),
        );
    }
}
