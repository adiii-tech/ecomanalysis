<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Actions\ApplyStockCount;
use App\Domain\Inventory\Actions\RecalculateReservations;
use App\Domain\Inventory\Actions\TransferStock;
use App\Domain\Inventory\Queries\StockQuery;
use App\Domain\Inventory\Services\BatchLedger;
use App\Domain\Inventory\Services\BundleAvailability;
use App\Domain\Inventory\Services\StockLedger;
use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Sku;
use App\Models\StockBatch;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Verdict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Stock a brand actually operates: levels, adjustments, the ledger behind every
 * change, warehouses, counts and transfers.
 */
class InventoryController extends Controller
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly StockQuery $stock,
    ) {}

    public function levels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
            'filter' => ['nullable', 'in:all,reorder,out_of_stock,overstock,untracked'],
        ]);

        return ApiResponse::ok([
            ...$this->stock->levels(
                $validated['location_id'] ?? null,
                $validated['q'] ?? null,
                $validated['filter'] ?? 'all',
            ),
            'locations' => $this->locationRows(),
            'reasons' => AdjustmentReason::catalogue(),
            'movement_types' => StockMovementType::catalogue(),
        ]);
    }

    /**
     * One adjustment against one SKU. Either an absolute count ("there are 40")
     * or a delta ("12 were damaged") — both end up as a movement with a reason.
     */
    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku_id' => ['required', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'mode' => ['required', 'in:set,delta'],
            'quantity' => ['required', 'integer', 'between:-1000000,1000000'],
            'reason' => ['required', Rule::in(array_column(AdjustmentReason::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $sku = Sku::query()->find($validated['sku_id']);

        if ($sku === null) {
            return ApiResponse::error('SKU not found.', 404);
        }

        $location = $this->resolveLocation($validated['location_id'] ?? null);

        try {
            $movement = $validated['mode'] === 'set'
                ? $this->ledger->setLevel($sku, $validated['quantity'], StockMovementType::Adjustment, $location, [
                    'reason' => $validated['reason'],
                    'note' => $validated['note'] ?? null,
                ])
                : $this->ledger->record($sku, $this->typeForReason($validated['reason']), $validated['quantity'], $location, [
                    'reason' => $validated['reason'],
                    'note' => $validated['note'] ?? null,
                ]);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        if ($movement === null) {
            return ApiResponse::ok(['changed' => false], message: 'Already at that quantity — nothing recorded.');
        }

        activity('inventory')->performedOn($sku)
            ->withProperties(['quantity' => $movement->quantity, 'reason' => $validated['reason'], 'balance' => $movement->balance_after])
            ->log('stock.adjusted');

        return ApiResponse::ok([
            'changed' => true,
            'balance_after' => $movement->balance_after,
        ], message: sprintf('%s is now at %d.', $sku->sku_code, $movement->balance_after));
    }

    /**
     * Opening stock for many SKUs at once — the first thing a brand needs when
     * they start managing inventory here.
     */
    public function bulkSetLevels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'reason' => ['nullable', Rule::in(array_column(AdjustmentReason::cases(), 'value'))],
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*.sku_code' => ['required', 'string', 'max:120'],
            'rows.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        $location = $this->resolveLocation($validated['location_id'] ?? null);
        $codes = collect($validated['rows'])->pluck('sku_code')->map(fn (string $c): string => trim($c));
        $skus = Sku::query()->whereIn('sku_code', $codes->all())->get()->keyBy('sku_code');

        $applied = 0;
        $unchanged = 0;
        $unknown = [];

        foreach ($validated['rows'] as $row) {
            $sku = $skus->get(trim($row['sku_code']));

            if ($sku === null) {
                $unknown[] = $row['sku_code'];

                continue;
            }

            try {
                $movement = $this->ledger->setLevel($sku, (int) $row['quantity'], StockMovementType::Opening, $location, [
                    'reason' => $validated['reason'] ?? AdjustmentReason::CountCorrection->value,
                    'note' => 'Bulk opening stock',
                ]);
            } catch (Throwable) {
                // A non-tracked SKU is skipped rather than failing the whole import.
                $unknown[] = $row['sku_code'];

                continue;
            }

            $movement === null ? $unchanged++ : $applied++;
        }

        activity('inventory')->withProperties(['applied' => $applied, 'unknown' => count($unknown)])->log('stock.bulk_set');

        return ApiResponse::ok([
            'applied' => $applied,
            'unchanged' => $unchanged,
            // Named explicitly, because silently dropping rows is how an import
            // convinces someone their stock is right when it is not.
            'unknown_skus' => array_values(array_unique($unknown)),
        ], message: sprintf('%d SKUs updated, %d already matched, %d not recognised.', $applied, $unchanged, count(array_unique($unknown))));
    }

    /** The ledger for one SKU: every movement, newest first. */
    public function movements(Request $request, int $sku): JsonResponse
    {
        $model = Sku::query()->find($sku);

        if ($model === null) {
            return ApiResponse::error('SKU not found.', 404);
        }

        $rows = StockMovement::query()
            ->with(['user:id,name', 'location:id,name'])
            ->where('sku_id', $sku)
            ->latest('happened_at')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(static fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->type->value,
                'type_label' => $movement->type->label(),
                'quantity' => $movement->quantity,
                'balance_after' => $movement->balance_after,
                'unit_cost' => $movement->unit_cost,
                'reason' => $movement->reason,
                'note' => $movement->note,
                'location' => $movement->location?->name,
                'by' => $movement->user?->name ?? 'System',
                'reference' => $movement->reference_type ? class_basename($movement->reference_type).' #'.$movement->reference_id : null,
                'happened_at' => $movement->happened_at?->toIso8601String(),
            ]);

        return ApiResponse::ok([
            'sku' => ['id' => $model->id, 'sku_code' => $model->sku_code, 'name' => $model->name],
            'on_hand' => $this->ledger->onHand($model),
            'rows' => $rows->all(),
        ]);
    }

    /** Replenishment settings a human owns, separate from what sales imply. */
    public function updateSettings(Request $request, int $sku): JsonResponse
    {
        $model = Sku::query()->find($sku);

        if ($model === null) {
            return ApiResponse::error('SKU not found.', 404);
        }

        $validated = $request->validate([
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_quantity' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'supplier_id' => ['nullable', 'integer'],
            'tracks_inventory' => ['sometimes', 'boolean'],
        ]);

        $model->forceFill($validated)->save();

        return ApiResponse::ok(null, message: 'Saved.');
    }

    public function locations(): JsonResponse
    {
        return ApiResponse::ok(['rows' => $this->locationRows()]);
    }

    public function saveLocation(Request $request, ?int $location = null): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:warehouse,store,3pl,virtual'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $model = $location !== null ? Location::query()->find($location) : null;

        if ($location !== null && $model === null) {
            return ApiResponse::error('Location not found.', 404);
        }

        $model ??= new Location(['tenant_id' => Tenant::id(), 'source' => StockLedger::SOURCE]);
        $model->forceFill($validated)->save();

        // Exactly one default, or posting without a location becomes ambiguous.
        if ($validated['is_default'] ?? false) {
            Location::query()->whereKeyNot($model->id)->update(['is_default' => false]);
        }

        return ApiResponse::ok(['id' => $model->id], message: 'Location saved.');
    }

    public function deleteLocation(int $location): JsonResponse
    {
        $model = Location::query()->find($location);

        if ($model === null) {
            return ApiResponse::error('Location not found.', 404);
        }

        $held = (int) Inventory::query()->where('location_id', $model->id)->sum('on_hand');

        if ($held !== 0) {
            return ApiResponse::error(
                sprintf('%s still holds %d units. Transfer them out before removing it.', $model->name, $held),
                422,
            );
        }

        $model->forceFill(['is_active' => false])->save();

        return ApiResponse::ok(null, message: 'Location deactivated.');
    }

    public function transfer(Request $request, TransferStock $transfer): JsonResponse
    {
        $validated = $request->validate([
            'sku_id' => ['required', 'integer'],
            'from_location_id' => ['required', 'integer'],
            'to_location_id' => ['required', 'integer', 'different:from_location_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $sku = Sku::query()->find($validated['sku_id']);
        $from = Location::query()->find($validated['from_location_id']);
        $to = Location::query()->find($validated['to_location_id']);

        if ($sku === null || $from === null || $to === null) {
            return ApiResponse::error('SKU or location not found.', 404);
        }

        try {
            $transfer->handle($sku, $from, $to, $validated['quantity'], $validated['note'] ?? null);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        activity('inventory')->performedOn($sku)
            ->withProperties(['from' => $from->name, 'to' => $to->name, 'quantity' => $validated['quantity']])
            ->log('stock.transferred');

        return ApiResponse::ok(null, message: sprintf('Moved %d × %s to %s.', $validated['quantity'], $sku->sku_code, $to->name));
    }

    public function counts(): JsonResponse
    {
        $rows = StockCount::query()
            ->withCount('items')
            ->with('location:id,name')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(static fn (StockCount $count): array => [
                'id' => $count->id,
                'reference' => $count->reference,
                'status' => $count->status,
                'scope' => $count->scope,
                'location' => $count->location?->name,
                'items' => (int) $count->getAttribute('items_count'),
                'created_at' => $count->created_at?->toIso8601String(),
                'applied_at' => $count->applied_at?->toIso8601String(),
            ]);

        return ApiResponse::ok(['rows' => $rows->all()]);
    }

    /**
     * Opens a count sheet, freezing what the system believes right now so the
     * variance later is measured against that moment.
     */
    public function createCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'scope' => ['required', 'in:full,category,reorder'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $location = $this->resolveLocation($validated['location_id'] ?? null);

        $skus = Sku::query()
            ->where('is_active', true)
            ->where('tracks_inventory', true)
            ->when($validated['scope'] === 'category' && filled($validated['category'] ?? null),
                fn ($q) => $q->where('category', $validated['category']))
            ->orderBy('sku_code')
            ->limit(5000)
            ->get();

        if ($skus->isEmpty()) {
            return ApiResponse::error('No stock-tracked SKUs match that scope.', 422);
        }

        $count = DB::transaction(function () use ($validated, $location, $skus): StockCount {
            $count = StockCount::query()->create([
                'tenant_id' => Tenant::id(),
                'location_id' => $location?->id ?? $this->ledger->defaultLocationId(),
                'created_by' => request()->user()?->id,
                'reference' => 'CNT-'.now()->format('ymd').'-'.str_pad((string) (StockCount::query()->count() + 1), 3, '0', STR_PAD_LEFT),
                'status' => 'open',
                'scope' => $validated['scope'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($skus as $sku) {
                StockCountItem::query()->create([
                    'tenant_id' => Tenant::id(),
                    'stock_count_id' => $count->id,
                    'sku_id' => $sku->id,
                    'expected_quantity' => $this->ledger->onHand($sku, $location),
                ]);
            }

            return $count;
        });

        return ApiResponse::ok([
            'id' => $count->id,
            'reference' => $count->reference,
            'items' => $skus->count(),
        ], message: sprintf('%s opened with %d SKUs to count.', $count->reference, $skus->count()));
    }

    public function count(int $count): JsonResponse
    {
        $model = StockCount::query()->with('location:id,name')->find($count);

        if ($model === null) {
            return ApiResponse::error('Count not found.', 404);
        }

        $items = $model->items()->with('sku:id,sku_code,name,cost_price')->get()->map(static fn (StockCountItem $item): array => [
            'id' => $item->id,
            'sku_id' => $item->sku_id,
            'sku_code' => $item->sku?->sku_code,
            'name' => $item->sku?->name,
            'expected_quantity' => $item->expected_quantity,
            'counted_quantity' => $item->counted_quantity,
            'variance' => $item->variance(),
            'variance_value' => $item->variance() === null ? null : $item->variance() * (int) ($item->sku?->cost_price ?? 0),
            'reason' => $item->reason,
        ]);

        return ApiResponse::ok([
            'count' => [
                'id' => $model->id,
                'reference' => $model->reference,
                'status' => $model->status,
                'location' => $model->location?->name,
                'applied_at' => $model->applied_at?->toIso8601String(),
            ],
            'items' => $items->all(),
            'summary' => [
                'counted' => $items->whereNotNull('counted_quantity')->count(),
                'total' => $items->count(),
                'variance_units' => (int) $items->sum(fn (array $row): int => (int) ($row['variance'] ?? 0)),
                'variance_value' => (int) $items->sum(fn (array $row): int => (int) ($row['variance_value'] ?? 0)),
            ],
            'reasons' => AdjustmentReason::catalogue(),
        ]);
    }

    public function saveCountItems(Request $request, int $count): JsonResponse
    {
        $model = StockCount::query()->find($count);

        if ($model === null) {
            return ApiResponse::error('Count not found.', 404);
        }

        if ($model->status === 'applied') {
            return ApiResponse::error('This count has been applied and can no longer be edited.', 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'max:5000'],
            'items.*.id' => ['required', 'integer'],
            'items.*.counted_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.reason' => ['nullable', Rule::in(array_column(AdjustmentReason::cases(), 'value'))],
        ]);

        foreach ($validated['items'] as $row) {
            StockCountItem::query()
                ->where('stock_count_id', $model->id)
                ->whereKey($row['id'])
                ->update([
                    'counted_quantity' => $row['counted_quantity'] ?? null,
                    'reason' => $row['reason'] ?? null,
                ]);
        }

        return ApiResponse::ok(null, message: 'Count saved.');
    }

    public function applyCount(Request $request, int $count, ApplyStockCount $apply): JsonResponse
    {
        $model = StockCount::query()->find($count);

        if ($model === null) {
            return ApiResponse::error('Count not found.', 404);
        }

        try {
            $result = $apply->handle($model, $request->user()?->id);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        activity('inventory')->performedOn($model)->withProperties($result)->log('stock_count.applied');

        return ApiResponse::ok($result, message: sprintf(
            '%d SKUs corrected (+%d / -%d units). %d were never counted and left alone.',
            $result['corrected'], $result['units_up'], $result['units_down'], $result['uncounted'],
        ));
    }

    /** Batches for one SKU, oldest-expiring first — the order they will leave in. */
    public function batches(Request $request, int $sku): JsonResponse
    {
        $model = Sku::query()->find($sku);

        if ($model === null) {
            return ApiResponse::error('SKU not found.', 404);
        }

        $rows = StockBatch::query()
            ->with('location:id,name')
            ->where('sku_id', $sku)
            ->orderByRaw('expires_on IS NULL, expires_on ASC, id ASC')
            ->get()
            ->map(static fn (StockBatch $batch): array => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'location' => $batch->location?->name,
                'quantity' => $batch->quantity,
                'quantity_received' => $batch->quantity_received,
                'unit_cost' => $batch->unit_cost,
                'value' => $batch->quantity * $batch->unit_cost,
                'expires_on' => $batch->expires_on?->toDateString(),
                'days_to_expiry' => $batch->daysToExpiry(),
                'is_expired' => $batch->isExpired(),
            ]);

        return ApiResponse::ok([
            'sku' => ['id' => $model->id, 'sku_code' => $model->sku_code, 'name' => $model->name],
            'tracks_batches' => (bool) $model->tracks_batches,
            'rows' => $rows->all(),
            'total_units' => (int) $rows->sum('quantity'),
            'total_value' => (int) $rows->sum('value'),
        ]);
    }

    /** Books a batch in by hand, for stock that did not arrive on a PO. */
    public function receiveBatch(Request $request, BatchLedger $batches): JsonResponse
    {
        $validated = $request->validate([
            'sku_id' => ['required', 'integer'],
            'batch_code' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'expires_on' => ['nullable', 'date'],
            'location_id' => ['nullable', 'integer'],
        ]);

        $sku = Sku::query()->find($validated['sku_id']);

        if ($sku === null) {
            return ApiResponse::error('SKU not found.', 404);
        }

        $location = $this->resolveLocation($validated['location_id'] ?? null);

        try {
            $batch = $batches->receive(
                $sku,
                $validated['batch_code'],
                $validated['quantity'],
                Money::fromRupees((float) ($validated['unit_cost'] ?? 0)),
                $location,
                $validated['expires_on'] ?? null,
            );

            // The batch and the overall balance move together, so the ledger
            // still explains the on-hand figure.
            $movement = $this->ledger->record($sku, StockMovementType::PurchaseReceipt, $validated['quantity'], $location, [
                'unit_cost' => $batch->unit_cost,
                'note' => sprintf('Batch %s', $batch->batch_code),
                'reference' => $batch,
            ]);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        $movement->forceFill(['stock_batch_id' => $batch->id])->save();

        activity('inventory')->performedOn($sku)
            ->withProperties(['batch' => $batch->batch_code, 'quantity' => $validated['quantity']])
            ->log('stock.batch_received');

        return ApiResponse::ok([
            'batch_id' => $batch->id,
            'balance_after' => $movement->balance_after,
        ], message: sprintf('Batch %s booked in. %s now at %d.', $batch->batch_code, $sku->sku_code, $movement->balance_after));
    }

    /** Batches at or near expiry — the stock you can still do something about. */
    public function expiring(Request $request, BatchLedger $batches): JsonResponse
    {
        $days = (int) $request->integer('within_days', 90);
        $rows = $batches->expiring($days);

        $atRisk = (int) $rows->sum(fn (StockBatch $batch): int => $batch->quantity * $batch->unit_cost);
        $expired = $rows->filter(fn (StockBatch $batch): bool => $batch->isExpired());

        return ApiResponse::ok([
            'rows' => $rows->map(static fn (StockBatch $batch): array => [
                'id' => $batch->id,
                'sku_code' => $batch->sku?->sku_code,
                'name' => $batch->sku?->name,
                'batch_code' => $batch->batch_code,
                'location' => $batch->location?->name,
                'quantity' => $batch->quantity,
                'expires_on' => $batch->expires_on?->toDateString(),
                'days_to_expiry' => $batch->daysToExpiry(),
                'is_expired' => $batch->isExpired(),
                'value_at_cost' => $batch->quantity * $batch->unit_cost,
            ])->values()->all(),
            'within_days' => $days,
            'value_at_risk' => $atRisk,
            'expired_units' => (int) $expired->sum('quantity'),
        ], verdict: $this->expiryVerdict($rows->all(), $atRisk));
    }

    /** Writes off a batch that has expired, with the loss recorded honestly. */
    public function writeOffBatch(Request $request, int $batch): JsonResponse
    {
        $model = StockBatch::query()->with('sku')->find($batch);

        if ($model === null) {
            return ApiResponse::error('Batch not found.', 404);
        }

        if ($model->quantity <= 0) {
            return ApiResponse::error('That batch is already empty.', 422);
        }

        $quantity = $model->quantity;

        try {
            $movement = $this->ledger->record($model->sku, StockMovementType::WriteOff, -$quantity, $model->location, [
                'reason' => 'expired',
                'note' => sprintf('Batch %s written off', $model->batch_code),
                'reference' => $model,
            ]);
        } catch (Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        $movement->forceFill(['stock_batch_id' => $model->id])->save();
        $model->forceFill(['quantity' => 0])->save();

        activity('inventory')->performedOn($model->sku)
            ->withProperties(['batch' => $model->batch_code, 'units' => $quantity, 'cost' => $quantity * $model->unit_cost])
            ->log('stock.batch_written_off');

        return ApiResponse::ok(null, message: sprintf(
            '%d units written off — %s of stock at cost.',
            $quantity,
            Money::format($quantity * $model->unit_cost),
        ));
    }

    /** What a bundle can be built from, and what is limiting it. */
    public function bundle(int $sku, BundleAvailability $bundles): JsonResponse
    {
        $model = Sku::query()->find($sku);

        if ($model === null) {
            return ApiResponse::error('SKU not found.', 404);
        }

        return ApiResponse::ok([
            'sku' => ['id' => $model->id, 'sku_code' => $model->sku_code, 'name' => $model->name],
            ...$bundles->forBundle($model),
        ]);
    }

    public function saveBundle(Request $request, int $sku, BundleAvailability $bundles): JsonResponse
    {
        $model = Sku::query()->find($sku);

        if ($model === null) {
            return ApiResponse::error('SKU not found.', 404);
        }

        $validated = $request->validate([
            'components' => ['present', 'array', 'max:50'],
            'components.*.sku_id' => ['required', 'integer'],
            'components.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $bundles->setComponents($model, $validated['components']);

        activity('inventory')->performedOn($model)
            ->withProperties(['components' => count($validated['components'])])
            ->log('bundle.saved');

        return ApiResponse::ok(
            $bundles->forBundle($model->fresh()),
            message: $validated['components'] === []
                ? 'Components cleared — this SKU holds its own stock again.'
                : 'Bundle saved. Its availability now follows its components.',
        );
    }

    /** Recomputes what open orders have spoken for. */
    public function recalculateReservations(RecalculateReservations $action): JsonResponse
    {
        $result = $action->handle();

        return ApiResponse::ok($result, message: sprintf(
            '%d SKUs have %d units reserved against orders that have not shipped.',
            $result['skus'],
            $result['reserved_units'],
        ));
    }

    /** @param list<StockBatch> $rows */
    private function expiryVerdict(array $rows, int $atRisk): Verdict
    {
        if ($rows === []) {
            return Verdict::good('Nothing is close to expiring', 'No batch has a date inside the window you asked about.');
        }

        $expired = array_values(array_filter($rows, static fn (StockBatch $batch): bool => $batch->isExpired()));

        if ($expired !== []) {
            $lost = array_sum(array_map(static fn (StockBatch $b): int => $b->quantity * $b->unit_cost, $expired));

            return Verdict::bad(
                sprintf('%d batches have already expired', count($expired)),
                sprintf('%s of stock at cost cannot be sold.', Money::format($lost)),
                'Write them off so your stock value stops counting goods you cannot ship.',
                $lost,
            );
        }

        return Verdict::watch(
            sprintf('%d batches expire soon', count($rows)),
            sprintf('%s of stock at cost is on a clock.', Money::format($atRisk)),
            'Discount or bundle them now — a markdown beats a write-off.',
        );
    }

    public function reconciliation(): JsonResponse
    {
        return ApiResponse::ok($this->stock->reconciliation());
    }

    /** @return list<array<string, mixed>> */
    private function locationRows(): array
    {
        return Location::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(static fn (Location $location): array => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
                'city' => $location->city,
                'state' => $location->state,
                'is_default' => (bool) $location->is_default,
                'units' => (int) Inventory::query()
                    ->where('location_id', $location->id)
                    ->where('source', StockLedger::SOURCE)
                    ->sum('on_hand'),
            ])
            ->all();
    }

    private function resolveLocation(?int $id): ?Location
    {
        return $id === null ? null : Location::query()->find($id);
    }

    /** Damage and write-offs deserve their own movement type, not a generic one. */
    private function typeForReason(string $reason): StockMovementType
    {
        return match ($reason) {
            AdjustmentReason::Damaged->value => StockMovementType::Damage,
            AdjustmentReason::Lost->value, AdjustmentReason::Stolen->value, AdjustmentReason::Expired->value => StockMovementType::WriteOff,
            default => StockMovementType::Adjustment,
        };
    }
}
