<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\CostAverager;
use App\Domain\Inventory\Services\StockLedger;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\Facades\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns a buyer's picked lines straight into purchase orders on their way to
 * suppliers — the handoff from "what to reorder" (Restock) to "what is now on
 * order" (Purchasing).
 *
 * Restock groups its selection by supplier before calling this, so each group
 * here becomes exactly one PO. Every PO is created and sent to the supplier in
 * the same breath: there is no draft stage, because a buyer who clicked "Issue"
 * already decided. Sending is what makes the ordered quantity count as
 * incoming, which is what lets Restock's own cover-days figure reflect the
 * order it was just used to create.
 *
 * @phpstan-type IssueLine array{sku_id: int, quantity: int, unit_cost: int}
 * @phpstan-type IssueGroup array{supplier_id: int, expected_at: ?string, items: list<IssueLine>}
 */
class IssuePurchaseOrders
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly CostAverager $costs,
        private readonly ?int $userId = null,
    ) {}

    /**
     * @param  list<IssueGroup>  $groups
     * @return list<array{id: int, po_number: string, supplier_id: int, total: int, items: int}>
     */
    public function handle(array $groups): array
    {
        return DB::transaction(function () use ($groups): array {
            $created = [];

            foreach ($groups as $group) {
                $created[] = $this->issueOne($group);
            }

            return $created;
        });
    }

    /**
     * @param  IssueGroup  $group
     * @return array{id: int, po_number: string, supplier_id: int, total: int, items: int}
     */
    private function issueOne(array $group): array
    {
        $landed = $this->costs->allocateLandedCost($group['items'], 0);

        $subtotal = array_sum(array_map(
            static fn (array $line): int => $line['quantity'] * $line['unit_cost'],
            $group['items'],
        ));

        $order = new PurchaseOrder([
            'tenant_id' => Tenant::id(),
            'created_by' => $this->userId,
            'po_number' => $this->nextPoNumber(),
            'supplier_id' => $group['supplier_id'],
            'location_id' => $this->ledger->defaultLocationId(),
            'status' => 'sent',
            'expected_at' => $group['expected_at'],
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'total' => $subtotal,
            'sent_at' => CarbonImmutable::now(),
        ]);
        $order->save();

        foreach ($group['items'] as $index => $line) {
            PurchaseOrderItem::query()->create([
                'tenant_id' => Tenant::id(),
                'purchase_order_id' => $order->id,
                'sku_id' => $line['sku_id'],
                'quantity_ordered' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'tax_rate' => 0,
                'line_total' => $line['quantity'] * $line['unit_cost'],
                'landed_unit_cost' => $landed[$index] ?? $line['unit_cost'],
            ]);

            // Incoming is what a reorder decision nets off against — bumping it
            // here is what stops the same shortage from being ordered twice.
            Inventory::query()->updateOrCreate(
                ['tenant_id' => Tenant::id(), 'sku_id' => $line['sku_id'], 'location_id' => $order->location_id, 'source' => StockLedger::SOURCE],
                ['incoming' => DB::raw('incoming + '.$line['quantity'])],
            );
        }

        return [
            'id' => $order->id,
            'po_number' => $order->po_number,
            'supplier_id' => (int) $group['supplier_id'],
            'total' => $subtotal,
            'items' => count($group['items']),
        ];
    }

    /**
     * Numbered fresh per order rather than once per batch, so three vendor
     * groups issued together still get three distinct, sequential numbers.
     */
    private function nextPoNumber(): string
    {
        $count = PurchaseOrder::query()->count() + 1;

        return 'PO-'.now()->format('ymd').'-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }
}
