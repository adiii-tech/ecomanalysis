<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\StockLedger;
use App\Enums\StockMovementType;
use App\Models\StockCount;
use App\Models\StockCountItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a finished count sheet to the books.
 *
 * A count is the moment the shelf overrules the system. Every line that differs
 * writes a correction movement carrying the counter's reason, so next quarter
 * you can ask where the shrinkage actually came from instead of guessing.
 */
class ApplyStockCount
{
    public function __construct(private readonly StockLedger $ledger) {}

    /** @return array{corrected: int, units_up: int, units_down: int, uncounted: int} */
    public function handle(StockCount $count, ?int $userId = null): array
    {
        if ($count->status === 'applied') {
            throw new RuntimeException('This count has already been applied.');
        }

        return DB::transaction(function () use ($count, $userId): array {
            $corrected = 0;
            $up = 0;
            $down = 0;
            $uncounted = 0;

            foreach ($count->items()->with('sku')->get() as $item) {
                /** @var StockCountItem $item */
                if ($item->counted_quantity === null) {
                    // A blank line means nobody counted it, which is not the
                    // same as counting zero.
                    $uncounted++;

                    continue;
                }

                $movement = $this->ledger->setLevel(
                    $item->sku,
                    $item->counted_quantity,
                    StockMovementType::CountCorrection,
                    $count->location,
                    [
                        'reason' => $item->reason,
                        'reference' => $count,
                        'user_id' => $userId,
                        'note' => sprintf('Counted %d against %d expected.', $item->counted_quantity, $item->expected_quantity),
                    ],
                );

                if ($movement === null) {
                    continue;
                }

                $corrected++;
                $movement->quantity > 0 ? $up += $movement->quantity : $down += abs($movement->quantity);
            }

            $count->forceFill(['status' => 'applied', 'applied_at' => now()])->save();

            return ['corrected' => $corrected, 'units_up' => $up, 'units_down' => $down, 'uncounted' => $uncounted];
        });
    }
}
