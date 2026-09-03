<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Support\Facades\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only way stock is allowed to change.
 *
 * Balance and ledger are written in one transaction under a row lock, so they
 * can never drift apart: if the movement exists, the balance moved with it. Two
 * people receiving the same PO at once therefore queue rather than race.
 */
class StockLedger
{
    /** Stock this system owns, kept apart from what a sales channel reports. */
    public const SOURCE = 'manual';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(
        Sku $sku,
        StockMovementType $type,
        int $quantity,
        ?Location $location = null,
        array $attributes = [],
    ): StockMovement {
        if ($quantity === 0) {
            throw new RuntimeException('A stock movement of zero says nothing; it is not recorded.');
        }

        if (! $sku->tracks_inventory) {
            throw new RuntimeException(sprintf('%s is not stock-tracked, so it has no balance to move.', $sku->sku_code));
        }

        $locationId = $location?->id ?? $this->defaultLocationId();

        return DB::transaction(function () use ($sku, $type, $quantity, $locationId, $attributes): StockMovement {
            $row = $this->lockedRow($sku, $locationId);
            $balance = $row->on_hand + $quantity;

            $movement = StockMovement::query()->create([
                'tenant_id' => Tenant::id(),
                'sku_id' => $sku->id,
                'location_id' => $locationId,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'unit_cost' => (int) ($attributes['unit_cost'] ?? 0),
                'reason' => $attributes['reason'] ?? null,
                'note' => $attributes['note'] ?? null,
                'reference_type' => isset($attributes['reference']) ? $attributes['reference']::class : null,
                'reference_id' => isset($attributes['reference']) ? $attributes['reference']->getKey() : null,
                'user_id' => $attributes['user_id'] ?? Auth::id(),
                'happened_at' => $attributes['happened_at'] ?? now(),
            ]);

            $row->forceFill([
                'on_hand' => $balance,
                // Available is what can still be promised to a customer.
                'available' => max(0, $balance - $row->reserved),
            ])->save();

            return $movement;
        });
    }

    /**
     * Moves stock to an exact number rather than by a delta — what a stock count
     * or a correction actually means. Returns null when nothing changed, so a
     * count that matches does not litter the ledger.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function setLevel(
        Sku $sku,
        int $countedQuantity,
        StockMovementType $type,
        ?Location $location = null,
        array $attributes = [],
    ): ?StockMovement {
        $current = $this->onHand($sku, $location);
        $delta = $countedQuantity - $current;

        if ($delta === 0) {
            return null;
        }

        return $this->record($sku, $type, $delta, $location, $attributes);
    }

    public function onHand(Sku $sku, ?Location $location = null): int
    {
        return (int) Inventory::query()
            ->where('sku_id', $sku->id)
            ->where('source', self::SOURCE)
            ->where('location_id', $location?->id ?? $this->defaultLocationId())
            ->value('on_hand');
    }

    /**
     * Reserves stock against an order so two customers cannot be promised the
     * same unit. Reservation never changes on_hand — the goods are still there.
     */
    public function reserve(Sku $sku, int $quantity, ?Location $location = null): void
    {
        $locationId = $location?->id ?? $this->defaultLocationId();

        DB::transaction(function () use ($sku, $quantity, $locationId): void {
            $row = $this->lockedRow($sku, $locationId);

            $reserved = max(0, $row->reserved + $quantity);

            $row->forceFill([
                'reserved' => $reserved,
                'available' => max(0, $row->on_hand - $reserved),
            ])->save();
        });
    }

    /**
     * The default location a tenant posts to when they have not said otherwise.
     * One is created on first use, because stock has to live somewhere.
     */
    public function defaultLocationId(): int
    {
        $existing = Location::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing->id;
        }

        return Location::query()->create([
            'tenant_id' => Tenant::id(),
            'source' => self::SOURCE,
            'name' => 'Main warehouse',
            'type' => 'warehouse',
            'is_active' => true,
            'is_default' => true,
        ])->id;
    }

    private function lockedRow(Sku $sku, int $locationId): Inventory
    {
        $row = Inventory::query()
            ->where('sku_id', $sku->id)
            ->where('location_id', $locationId)
            ->where('source', self::SOURCE)
            ->lockForUpdate()
            ->first();

        return $row ?? Inventory::query()->create([
            'tenant_id' => Tenant::id(),
            'sku_id' => $sku->id,
            'location_id' => $locationId,
            'source' => self::SOURCE,
            'on_hand' => 0,
            'reserved' => 0,
            'available' => 0,
            'incoming' => 0,
        ]);
    }
}
