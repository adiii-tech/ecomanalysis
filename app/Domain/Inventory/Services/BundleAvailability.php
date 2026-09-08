<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Models\Location;
use App\Models\Sku;
use App\Models\SkuComponent;
use App\Support\Facades\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * How many of a bundle you can actually ship.
 *
 * A bundle holds no stock of its own — it is only as available as its scarcest
 * component allows. Counting a bundle's own on-hand number would let you sell
 * combos you cannot pack.
 */
class BundleAvailability
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @return array{buildable: int, limiting_sku: string|null, components: list<array<string, mixed>>}
     */
    public function forBundle(Sku $bundle, ?Location $location = null): array
    {
        $components = SkuComponent::query()
            ->with('child:id,sku_code,name,cost_price')
            ->where('parent_sku_id', $bundle->id)
            ->get();

        if ($components->isEmpty()) {
            return ['buildable' => 0, 'limiting_sku' => null, 'components' => []];
        }

        $rows = [];
        $buildable = null;
        $limiting = null;

        foreach ($components as $component) {
            $child = $component->child;

            if ($child === null) {
                continue;
            }

            $onHand = $this->ledger->onHand($child, $location);
            $required = max(1, (int) $component->quantity);
            $possible = intdiv($onHand, $required);

            $rows[] = [
                'sku_id' => $child->id,
                'sku_code' => $child->sku_code,
                'name' => $child->name,
                'required_per_bundle' => $required,
                'on_hand' => $onHand,
                'builds' => $possible,
                'cost_price' => (int) $child->cost_price,
            ];

            if ($buildable === null || $possible < $buildable) {
                $buildable = $possible;
                $limiting = $child->sku_code;
            }
        }

        return [
            'buildable' => $buildable ?? 0,
            'limiting_sku' => $limiting,
            'components' => $rows,
        ];
    }

    /**
     * Buildable counts for every bundle in one pass, for the stock table.
     *
     * @return array<int, array{buildable: int, limiting_sku: string|null}>
     */
    public function forAllBundles(?Location $location = null): array
    {
        $locationId = $location?->id;

        $rows = DB::table('sku_components as c')
            ->join('skus as child', 'child.id', '=', 'c.child_sku_id')
            ->leftJoin('inventory as i', function ($join) use ($locationId): void {
                $join->on('i.sku_id', '=', 'c.child_sku_id')
                    ->where('i.source', StockLedger::SOURCE)
                    ->when($locationId !== null, fn ($q) => $q->where('i.location_id', $locationId));
            })
            ->where('c.tenant_id', Tenant::id())
            ->selectRaw('c.parent_sku_id, child.sku_code, c.quantity AS required')
            ->selectRaw('COALESCE(MAX(i.on_hand), 0) AS on_hand')
            ->groupBy('c.parent_sku_id', 'child.sku_code', 'c.quantity')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $possible = intdiv((int) $row->on_hand, max(1, (int) $row->required));
            $parentId = (int) $row->parent_sku_id;

            if (! isset($result[$parentId]) || $possible < $result[$parentId]['buildable']) {
                $result[$parentId] = ['buildable' => $possible, 'limiting_sku' => $row->sku_code];
            }
        }

        return $result;
    }

    /**
     * Saves a bundle's recipe.
     *
     * @param  list<array{sku_id: int, quantity: int}>  $components
     */
    public function setComponents(Sku $bundle, array $components): void
    {
        DB::transaction(function () use ($bundle, $components): void {
            SkuComponent::query()->where('parent_sku_id', $bundle->id)->delete();

            foreach ($components as $component) {
                if ((int) $component['sku_id'] === $bundle->id) {
                    // A bundle containing itself would make availability
                    // infinite and unbuildable at the same time.
                    continue;
                }

                SkuComponent::query()->create([
                    'tenant_id' => Tenant::id(),
                    'parent_sku_id' => $bundle->id,
                    'child_sku_id' => (int) $component['sku_id'],
                    'quantity' => max(1, (int) $component['quantity']),
                ]);
            }

            // A bundle is assembled from components, so it never carries stock
            // of its own.
            $bundle->forceFill([
                'is_combo' => $components !== [],
                'tracks_inventory' => $components === [],
            ])->save();
        });
    }
}
