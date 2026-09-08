<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\StockLedger;
use App\Models\Inventory;
use App\Support\Facades\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes how much stock is spoken for by orders that have not shipped.
 *
 * Reservation is derived, not event-driven: it is recalculated from the open
 * orders themselves rather than incremented and decremented as things happen.
 * An incremental counter drifts the first time a webhook is missed or a job
 * retries, and a drifted reservation quietly stops you selling stock you have.
 */
class RecalculateReservations
{
    /** Orders in these states are promised to a customer but not yet gone. */
    private const OPEN_STATUSES = ['placed', 'confirmed'];

    /** @return array{skus: int, reserved_units: int} */
    public function handle(): array
    {
        $demand = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.tenant_id', Tenant::id())
            ->whereIn('o.status', self::OPEN_STATUSES)
            ->selectRaw('oi.sku_id, COALESCE(SUM(oi.qty - oi.returned_qty), 0) AS units')
            ->whereNotNull('oi.sku_id')
            ->groupBy('oi.sku_id')
            ->pluck('units', 'sku_id');

        $updated = 0;
        $units = 0;

        DB::transaction(function () use ($demand, &$updated, &$units): void {
            // Everything the ledger manages starts from zero, so a SKU that no
            // longer has open orders is released rather than left reserved.
            Inventory::query()
                ->where('source', StockLedger::SOURCE)
                ->where('reserved', '>', 0)
                ->whereNotIn('sku_id', $demand->keys()->all())
                ->update(['reserved' => 0, 'available' => DB::raw('on_hand')]);

            foreach ($demand as $skuId => $reserved) {
                $reserved = (int) $reserved;

                $rows = Inventory::query()
                    ->where('source', StockLedger::SOURCE)
                    ->where('sku_id', $skuId)
                    ->get();

                foreach ($rows as $row) {
                    $row->forceFill([
                        'reserved' => $reserved,
                        'available' => max(0, $row->on_hand - $reserved),
                    ])->save();
                }

                if ($rows->isNotEmpty()) {
                    $updated++;
                    $units += $reserved;
                }
            }
        });

        return ['skus' => $updated, 'reserved_units' => $units];
    }
}
