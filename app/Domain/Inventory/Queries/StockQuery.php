<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Queries;

use App\Domain\Inventory\Services\StockLedger;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads for the stock screens. Managed stock and channel-reported stock are
 * kept visibly separate: when they disagree the product says so rather than
 * quietly picking one.
 */
class StockQuery
{
    /**
     * @return array<string, mixed>
     */
    public function levels(?int $locationId = null, ?string $search = null, string $filter = 'all'): array
    {
        $rows = DB::table('skus as s')
            ->leftJoin('inventory as i', function ($join) use ($locationId): void {
                $join->on('i.sku_id', '=', 's.id')
                    ->where('i.source', StockLedger::SOURCE)
                    ->when($locationId !== null, fn ($q) => $q->where('i.location_id', $locationId));
            })
            ->leftJoin('suppliers as sup', 'sup.id', '=', 's.supplier_id')
            ->leftJoin('sku_daily_rollup as r', function ($join): void {
                $join->on('r.sku_id', '=', 's.id')
                    ->where('r.date', '>=', now(Tenant::timezone())->subDays(30)->toDateString());
            })
            ->where('s.tenant_id', Tenant::id())
            ->where('s.is_active', true)
            ->when($search !== null && $search !== '', fn ($q) => $q->where(function ($inner) use ($search): void {
                $inner->where('s.sku_code', 'like', '%'.$search.'%')
                    ->orWhere('s.name', 'like', '%'.$search.'%')
                    ->orWhere('s.barcode', 'like', '%'.$search.'%');
            }))
            ->selectRaw('s.id, s.sku_code, s.name, s.category, s.image_url, s.barcode, s.cost_price, s.selling_price')
            ->selectRaw('s.reorder_point, s.safety_stock, s.reorder_quantity, s.lead_time_days, s.tracks_inventory')
            ->selectRaw('sup.name AS supplier_name')
            ->selectRaw('COALESCE(MAX(i.on_hand), 0) AS on_hand, COALESCE(MAX(i.reserved), 0) AS reserved')
            ->selectRaw('COALESCE(MAX(i.available), 0) AS available, COALESCE(MAX(i.incoming), 0) AS incoming')
            ->selectRaw('COALESCE(SUM(r.units_sold), 0) AS units_30d')
            ->groupBy('s.id', 's.sku_code', 's.name', 's.category', 's.image_url', 's.barcode',
                's.cost_price', 's.selling_price', 's.reorder_point', 's.safety_stock',
                's.reorder_quantity', 's.lead_time_days', 's.tracks_inventory', 'sup.name')
            ->orderBy('s.sku_code')
            ->limit(2000)
            ->get()
            ->map(static function (object $row): array {
                $onHand = (int) $row->on_hand;
                $dailyRate = round(Num::safeDivide((int) $row->units_30d, 30), 3);
                $cover = $dailyRate > 0 ? round($onHand / $dailyRate, 1) : ($onHand > 0 ? 999.0 : 0.0);

                // A reorder point set by a human beats one inferred from sales.
                $reorderPoint = $row->reorder_point !== null
                    ? (int) $row->reorder_point
                    : (int) ceil($dailyRate * max(1, (int) ($row->lead_time_days ?? 7)));

                return [
                    'sku_id' => (int) $row->id,
                    'sku_code' => $row->sku_code,
                    'name' => $row->name,
                    'category' => $row->category,
                    'image_url' => $row->image_url,
                    'barcode' => $row->barcode,
                    'supplier_name' => $row->supplier_name,
                    'tracks_inventory' => (bool) $row->tracks_inventory,
                    'on_hand' => $onHand,
                    'reserved' => (int) $row->reserved,
                    'available' => (int) $row->available,
                    'incoming' => (int) $row->incoming,
                    'cost_price' => (int) $row->cost_price,
                    'selling_price' => (int) $row->selling_price,
                    'stock_value' => $onHand * (int) $row->cost_price,
                    'units_30d' => (int) $row->units_30d,
                    'daily_rate' => $dailyRate,
                    'days_of_cover' => $cover,
                    'reorder_point' => $reorderPoint,
                    'reorder_point_is_manual' => $row->reorder_point !== null,
                    'safety_stock' => $row->safety_stock === null ? null : (int) $row->safety_stock,
                    'reorder_quantity' => $row->reorder_quantity === null ? null : (int) $row->reorder_quantity,
                    'lead_time_days' => $row->lead_time_days === null ? null : (int) $row->lead_time_days,
                    'needs_reorder' => $row->tracks_inventory && $onHand <= $reorderPoint,
                ];
            });

        $filtered = match ($filter) {
            'reorder' => $rows->where('needs_reorder', true),
            'out_of_stock' => $rows->where('on_hand', '<=', 0)->where('tracks_inventory', true),
            'overstock' => $rows->where('days_of_cover', '>', 120)->where('on_hand', '>', 0),
            'untracked' => $rows->where('tracks_inventory', false),
            default => $rows,
        };

        return [
            'rows' => $filtered->values()->all(),
            'summary' => $this->summarise($rows),
            'verdict' => $this->verdict($rows)->toArray(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarise(Collection $rows): array
    {
        $tracked = $rows->where('tracks_inventory', true);

        return [
            'skus' => $tracked->count(),
            'stock_value' => (int) $tracked->sum('stock_value'),
            'units' => (int) $tracked->sum('on_hand'),
            'reserved' => (int) $tracked->sum('reserved'),
            'out_of_stock' => $tracked->where('on_hand', '<=', 0)->count(),
            'needs_reorder' => $tracked->where('needs_reorder', true)->count(),
            'overstock' => $tracked->where('days_of_cover', '>', 120)->where('on_hand', '>', 0)->count(),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function verdict(Collection $rows): Verdict
    {
        $tracked = $rows->where('tracks_inventory', true);

        if ($tracked->isEmpty()) {
            return Verdict::neutral(
                'No stock is being managed here yet',
                'Add a SKU, or set opening stock on the ones you already have.',
            );
        }

        $out = $tracked->where('on_hand', '<=', 0)->where('units_30d', '>', 0);

        if ($out->isNotEmpty()) {
            $lost = (int) $out->sum(static fn (array $row): int => (int) round($row['daily_rate'] * 30 * $row['selling_price']));

            return Verdict::bad(
                sprintf('%d SKUs that were selling are at zero', $out->count()),
                sprintf('Roughly %s of monthly demand has nothing to ship against it.', Money::format($lost)),
                'Raise a purchase order for these first — they had proven demand.',
                $lost,
            );
        }

        $reorder = $tracked->where('needs_reorder', true);

        if ($reorder->isNotEmpty()) {
            return Verdict::watch(
                sprintf('%d SKUs are at or below their reorder point', $reorder->count()),
                sprintf('%s of stock value on hand across the catalogue.', Money::format((int) $tracked->sum('stock_value'))),
                'Create a purchase order before the fastest movers run dry.',
            );
        }

        $dead = $tracked->where('units_30d', 0)->where('on_hand', '>', 0);

        if ($dead->count() > $tracked->count() * 0.3) {
            return Verdict::watch(
                sprintf('%d of %d SKUs have not moved in 30 days', $dead->count(), $tracked->count()),
                sprintf('%s of cost is sitting still.', Money::format((int) $dead->sum('stock_value'))),
                'Bundle or discount them — that cash is doing nothing on a shelf.',
            );
        }

        return Verdict::good(
            'Stock cover looks healthy',
            sprintf('%s across %d SKUs, nothing below its reorder point.',
                Money::format((int) $tracked->sum('stock_value')), $tracked->count()),
        );
    }

    /**
     * Where managed stock and the sales channel disagree. This is the number
     * that quietly causes oversells, so it gets its own screen.
     *
     * @return array<string, mixed>
     */
    public function reconciliation(): array
    {
        $rows = DB::table('skus as s')
            ->join('inventory as mine', function ($join): void {
                $join->on('mine.sku_id', '=', 's.id')->where('mine.source', StockLedger::SOURCE);
            })
            ->join('inventory as theirs', function ($join): void {
                $join->on('theirs.sku_id', '=', 's.id')->where('theirs.source', '!=', StockLedger::SOURCE);
            })
            ->where('s.tenant_id', Tenant::id())
            ->whereColumn('mine.on_hand', '!=', 'theirs.on_hand')
            ->selectRaw('s.id AS sku_id, s.sku_code, s.name, s.cost_price')
            ->selectRaw('mine.on_hand AS managed, theirs.on_hand AS channel, theirs.source AS channel_source')
            ->selectRaw('theirs.synced_at')
            ->orderByRaw('ABS(mine.on_hand - theirs.on_hand) DESC')
            ->limit(500)
            ->get()
            ->map(static fn (object $row): array => [
                'sku_id' => (int) $row->sku_id,
                'sku_code' => $row->sku_code,
                'name' => $row->name,
                'managed' => (int) $row->managed,
                'channel' => (int) $row->channel,
                'channel_source' => $row->channel_source,
                'difference' => (int) $row->managed - (int) $row->channel,
                'value_at_risk' => abs((int) $row->managed - (int) $row->channel) * (int) $row->cost_price,
                'synced_at' => $row->synced_at,
            ]);

        return [
            'rows' => $rows->all(),
            'count' => $rows->count(),
            'caveat' => 'Stock you manage here is never pushed to a sales channel automatically. These are the SKUs where the two disagree — the channel is what customers can actually buy against.',
        ];
    }
}
