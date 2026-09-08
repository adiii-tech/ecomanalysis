<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Models\Location;
use App\Models\Sku;
use App\Models\StockBatch;
use App\Support\Facades\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Batch-level stock for SKUs that need it.
 *
 * Consumption is first-expiry-first-out: the units closest to expiring leave
 * first, which is what a warehouse actually does and what keeps write-offs
 * down. Batches with no expiry fall back to oldest-received, which is FIFO.
 */
class BatchLedger
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * Books a batch in. The batch carries its own cost, so valuation later can
     * say what these specific units cost rather than an average.
     */
    public function receive(
        Sku $sku,
        string $batchCode,
        int $quantity,
        int $unitCost,
        ?Location $location = null,
        ?string $expiresOn = null,
        ?Model $source = null,
    ): StockBatch {
        if ($quantity <= 0) {
            throw new RuntimeException('A batch receipt must be positive.');
        }

        $locationId = $location?->id ?? $this->ledger->defaultLocationId();

        return DB::transaction(function () use ($sku, $batchCode, $quantity, $unitCost, $locationId, $expiresOn, $source): StockBatch {
            $batch = StockBatch::query()->firstOrNew([
                'tenant_id' => Tenant::id(),
                'sku_id' => $sku->id,
                'location_id' => $locationId,
                'batch_code' => $batchCode,
            ]);

            $batch->forceFill([
                'quantity' => $batch->quantity + $quantity,
                'quantity_received' => $batch->quantity_received + $quantity,
                'unit_cost' => $unitCost > 0 ? $unitCost : $batch->unit_cost,
                'expires_on' => $expiresOn ?? $batch->expires_on ?? $this->impliedExpiry($sku),
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ])->save();

            return $batch;
        });
    }

    /**
     * Takes units out, oldest-expiring first.
     *
     * Returns what came from where, so the caller can cost the consumption
     * correctly instead of guessing at an average.
     *
     * @return list<array{batch_id: int, batch_code: string, quantity: int, unit_cost: int}>
     */
    public function consume(Sku $sku, int $quantity, ?Location $location = null): array
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Consumption must be positive.');
        }

        $locationId = $location?->id ?? $this->ledger->defaultLocationId();

        return DB::transaction(function () use ($sku, $quantity, $locationId): array {
            $batches = StockBatch::query()
                ->where('sku_id', $sku->id)
                ->where('location_id', $locationId)
                ->where('quantity', '>', 0)
                // Expiring stock leaves first; undated batches fall to the back
                // in the order they arrived.
                ->orderByRaw('expires_on IS NULL, expires_on ASC, id ASC')
                ->lockForUpdate()
                ->get();

            $available = (int) $batches->sum('quantity');

            if ($available < $quantity) {
                throw new RuntimeException(sprintf(
                    '%s has %d units across its batches; cannot take %d.',
                    $sku->sku_code,
                    $available,
                    $quantity,
                ));
            }

            $remaining = $quantity;
            $taken = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (int) $batch->quantity);
                $batch->forceFill(['quantity' => $batch->quantity - $take])->save();

                $taken[] = [
                    'batch_id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'quantity' => $take,
                    'unit_cost' => (int) $batch->unit_cost,
                ];

                $remaining -= $take;
            }

            return $taken;
        });
    }

    /**
     * What the stock on hand actually cost, batch by batch — the FIFO answer
     * rather than units × today's average cost.
     */
    public function valuation(?Location $location = null): int
    {
        return (int) StockBatch::query()
            ->when($location !== null, fn ($q) => $q->where('location_id', $location->id))
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) AS value')
            ->value('value');
    }

    /**
     * Batches at or near expiry, worst first.
     *
     * @return Collection<int, StockBatch>
     */
    public function expiring(int $withinDays = 90): Collection
    {
        return StockBatch::query()
            ->with(['sku:id,sku_code,name,selling_price', 'location:id,name'])
            ->where('quantity', '>', 0)
            ->whereNotNull('expires_on')
            ->where('expires_on', '<=', now()->addDays($withinDays)->toDateString())
            ->orderBy('expires_on')
            ->limit(500)
            ->get();
    }

    /** A shelf life on the SKU is the fallback when a receipt carries no date. */
    private function impliedExpiry(Sku $sku): ?string
    {
        return $sku->shelf_life_days === null
            ? null
            : now()->addDays((int) $sku->shelf_life_days)->toDateString();
    }
}
