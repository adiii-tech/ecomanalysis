<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Models\Sku;
use App\Models\SkuCostHistory;
use App\Support\Facades\Tenant;
use App\Support\Money;

/**
 * Weighted-average costing on receipt.
 *
 * Buying 100 units at ₹80 when 50 at ₹100 are already on the shelf should leave
 * the cost at ₹86.67, not ₹80 — otherwise margin jumps the moment a cheap
 * shipment lands and every historical comparison lies. Each change is written
 * to the cost history so old orders keep costing what they actually cost.
 */
class CostAverager
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @return int the new cost price in paise
     */
    public function applyReceipt(Sku $sku, int $quantityReceived, int $landedUnitCost): int
    {
        if ($quantityReceived <= 0 || $landedUnitCost <= 0) {
            return (int) $sku->cost_price;
        }

        $existingUnits = max(0, $this->ledger->onHand($sku));
        $existingCost = (int) $sku->cost_price;

        // With nothing on hand there is nothing to average against.
        $newCost = $existingUnits === 0
            ? $landedUnitCost
            : (int) round((($existingUnits * $existingCost) + ($quantityReceived * $landedUnitCost))
                / ($existingUnits + $quantityReceived));

        if ($newCost === $existingCost) {
            return $existingCost;
        }

        $sku->forceFill(['cost_price' => $newCost])->save();

        // One entry per SKU per day, matching the cost editor, so a second
        // receipt the same day updates rather than duplicates.
        SkuCostHistory::query()->updateOrCreate(
            [
                'tenant_id' => Tenant::id(),
                'sku_id' => $sku->id,
                'effective_from' => now()->toDateString(),
            ],
            [
                'cost_price' => $newCost,
                'note' => sprintf(
                    'Received %d at %s; weighted against %d on hand at %s.',
                    $quantityReceived,
                    Money::format($landedUnitCost),
                    $existingUnits,
                    Money::format($existingCost),
                ),
            ],
        );

        return $newCost;
    }

    /**
     * Spreads shipment-level costs across lines by value, which is the closest
     * honest allocation when freight is charged for the whole consignment.
     *
     * @param  list<array{sku_id: int, quantity: int, unit_cost: int}>  $lines
     * @return array<int, int> landed unit cost keyed by line index
     */
    public function allocateLandedCost(array $lines, int $freightAndOther): array
    {
        $subtotal = array_sum(array_map(
            static fn (array $line): int => $line['quantity'] * $line['unit_cost'],
            $lines,
        ));

        $landed = [];

        foreach ($lines as $index => $line) {
            $lineValue = $line['quantity'] * $line['unit_cost'];

            $share = $subtotal > 0 && $freightAndOther > 0
                ? (int) round($freightAndOther * ($lineValue / $subtotal))
                : 0;

            $landed[$index] = $line['quantity'] > 0
                ? $line['unit_cost'] + (int) round($share / $line['quantity'])
                : $line['unit_cost'];
        }

        return $landed;
    }
}
