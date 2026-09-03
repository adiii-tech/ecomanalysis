<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\CostAverager;
use App\Domain\Inventory\Services\StockLedger;
use App\Enums\StockMovementType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Books goods in against a purchase order.
 *
 * Receiving is where three things have to happen together or not at all: the
 * stock moves, the PO's outstanding quantity drops, and the SKU's cost is
 * re-averaged at the landed price. Doing them in one transaction is what keeps
 * margin honest the next morning.
 */
class ReceivePurchaseOrder
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly CostAverager $costs,
    ) {}

    /**
     * @param  array<int, int>  $received  quantity received, keyed by PO item id
     * @return array{received: int, lines: int, status: string}
     */
    public function handle(PurchaseOrder $order, array $received, ?string $note = null): array
    {
        if (! $order->isReceivable()) {
            throw new RuntimeException(sprintf(
                'A %s purchase order cannot be received. Send it to the supplier first.',
                $order->status,
            ));
        }

        return DB::transaction(function () use ($order, $received, $note): array {
            $items = $order->items()->with('sku')->get()->keyBy('id');
            $lines = 0;
            $units = 0;

            foreach ($received as $itemId => $quantity) {
                $item = $items->get($itemId);
                $quantity = (int) $quantity;

                if ($item === null || $quantity <= 0) {
                    continue;
                }

                // Over-receiving is usually a typo, and silently accepting it
                // would inflate stock that never arrived.
                if ($quantity > $item->quantityOutstanding()) {
                    throw new RuntimeException(sprintf(
                        '%s: trying to receive %d but only %d are outstanding.',
                        $item->sku?->sku_code ?? 'Line',
                        $quantity,
                        $item->quantityOutstanding(),
                    ));
                }

                // Average against what was on the shelf *before* this delivery.
                // Booking the stock in first would let the new units weight
                // themselves and drag the cost toward the price just paid.
                $this->costs->applyReceipt($item->sku, $quantity, (int) $item->landed_unit_cost);

                $this->ledger->record(
                    $item->sku,
                    StockMovementType::PurchaseReceipt,
                    $quantity,
                    $order->location,
                    [
                        'unit_cost' => (int) $item->landed_unit_cost,
                        'reference' => $order,
                        'note' => $note,
                    ],
                );

                $item->forceFill(['quantity_received' => $item->quantity_received + $quantity])->save();

                $lines++;
                $units += $quantity;
            }

            $order->forceFill([
                'status' => $this->statusFor($order),
                'received_at' => now(),
            ])->save();

            return ['received' => $units, 'lines' => $lines, 'status' => $order->status];
        });
    }

    private function statusFor(PurchaseOrder $order): string
    {
        $outstanding = $order->items()->get()
            ->sum(fn (PurchaseOrderItem $item): int => $item->quantityOutstanding());

        return $outstanding === 0 ? 'received' : 'partial';
    }
}
