<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\StockLedger;
use App\Enums\StockMovementType;
use App\Models\Location;
use App\Models\Sku;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves stock between locations as a matched pair of movements, so the total
 * across the business never changes even for an instant.
 */
class TransferStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    /** @return array{out: StockMovement, in: StockMovement} */
    public function handle(Sku $sku, Location $from, Location $to, int $quantity, ?string $note = null): array
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Transfer quantity must be positive.');
        }

        if ($from->id === $to->id) {
            throw new RuntimeException('Source and destination are the same location.');
        }

        $available = $this->ledger->onHand($sku, $from);

        if ($available < $quantity) {
            throw new RuntimeException(sprintf(
                '%s has only %d at %s; cannot transfer %d.',
                $sku->sku_code,
                $available,
                $from->name,
                $quantity,
            ));
        }

        return DB::transaction(fn (): array => [
            'out' => $this->ledger->record($sku, StockMovementType::TransferOut, -$quantity, $from, [
                'note' => $note ?? sprintf('Transferred to %s', $to->name),
            ]),
            'in' => $this->ledger->record($sku, StockMovementType::TransferIn, $quantity, $to, [
                'note' => $note ?? sprintf('Transferred from %s', $from->name),
            ]),
        ]);
    }
}
