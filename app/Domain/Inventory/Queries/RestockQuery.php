<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Queries;

use App\Domain\Inventory\Services\StockLedger;
use App\Support\Facades\Tenant;
use App\Support\Num;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The restock desk: where inventory money sits, and what to buy next.
 *
 * Every number is derived from the same settings the buyer can see and change —
 * sales window, lead time, safety and target cover — so a suggested quantity can
 * always be argued with rather than trusted blindly. Sales come from the SKU
 * rollup and are netted of returns unless the caller asks for gross.
 *
 * @phpstan-type RestockSettings array{window:int, lead:int, safety:int, target:int, over:int, dead:int, newDays:int, round:int, projPerDay:float, best:string, bestTopOnly:bool, exclude:list<string>, netReturns:bool, costMode:string, costValue:float}
 */
class RestockQuery
{
    /** Buckets in the order a buyer should work through them. */
    public const BUCKET_ORDER = ['reorder', 'soon', 'dead', 'overstock', 'healthy', 'new', 'inactive', 'excluded'];

    /**
     * @param  RestockSettings  $s
     * @return array<string, mixed>
     */
    public function handle(array $s): array
    {
        $anchor = $this->anchor();
        $span = $this->historySpan($anchor);
        $weff = max(1, min($s['window'], $span));

        $sales = $this->sales($anchor, $s);
        $stock = $this->stock();
        $rows = $this->rows($this->skus(), $sales, $stock, $anchor, $weff, $s);

        $this->rankAbc($rows);
        $this->rankBestsellers($rows);

        return [
            'rows' => array_map(static fn (array $r): array => $r['out'], $rows),
            'summary' => $this->summary($rows, $s),
            'buckets' => $this->buckets($rows),
            'categories' => $this->categories($rows),
            'health' => $this->health($rows, $anchor, $span, $weff, $s),
            'settings' => $s,
            'anchor' => $anchor->toDateString(),
            'window_days_used' => $weff,
            'history_days' => $span,
        ];
    }

    /**
     * Sales are anchored to the last day with data, not to today: a rollup that
     * is a day behind should not read as a day of zero sales.
     */
    private function anchor(): CarbonImmutable
    {
        $last = DB::table('sku_daily_rollup')->where('tenant_id', Tenant::id())->max('date');

        return $last !== null
            ? CarbonImmutable::parse((string) $last, Tenant::timezone())
            : CarbonImmutable::now(Tenant::timezone());
    }

    private function historySpan(CarbonImmutable $anchor): int
    {
        $first = DB::table('sku_daily_rollup')->where('tenant_id', Tenant::id())->min('date');

        if ($first === null) {
            return 0;
        }

        return (int) CarbonImmutable::parse((string) $first, Tenant::timezone())->diffInDays($anchor) + 1;
    }

    /**
     * @param  RestockSettings  $s
     * @return Collection<int, \stdClass>
     */
    private function sales(CarbonImmutable $anchor, array $s): Collection
    {
        $winStart = $anchor->subDays(max(1, $s['window']) - 1)->toDateString();
        $d30 = $anchor->subDays(29)->toDateString();
        $d60 = $anchor->subDays(59)->toDateString();
        $bestStart = $s['best'] === 'all' ? '1970-01-01' : $anchor->subDays(max(1, (int) $s['best']) - 1)->toDateString();
        $earliest = min($winStart, $d60, $bestStart);

        // Returns are netted off the same day they were booked against, which is
        // how the rollup stores them; counting them as sales only ever inflates.
        // Both columns are unsigned, so the subtraction has to be cast — a day
        // with more returns than sales would otherwise overflow rather than net.
        $units = $s['netReturns']
            ? '(CAST(units_sold AS SIGNED) - CAST(returned_units AS SIGNED))'
            : 'units_sold';

        return DB::table('sku_daily_rollup')
            ->where('tenant_id', Tenant::id())
            ->where('date', '>=', $earliest)
            ->groupBy('sku_id')
            ->selectRaw('sku_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN date >= ? THEN {$units} ELSE 0 END), 0) AS units_w", [$winStart])
            ->selectRaw('COALESCE(SUM(CASE WHEN date >= ? THEN returned_units ELSE 0 END), 0) AS returns_w', [$winStart])
            ->selectRaw('COALESCE(SUM(CASE WHEN date >= ? THEN net_sales ELSE 0 END), 0) AS revenue_w', [$winStart])
            ->selectRaw("COALESCE(SUM(CASE WHEN date >= ? THEN {$units} ELSE 0 END), 0) AS units_30", [$d30])
            ->selectRaw("COALESCE(SUM(CASE WHEN date >= ? AND date < ? THEN {$units} ELSE 0 END), 0) AS units_prev_30", [$d60, $d30])
            ->selectRaw("COALESCE(SUM(CASE WHEN date >= ? THEN {$units} ELSE 0 END), 0) AS units_best", [$bestStart])
            ->selectRaw('MAX(CASE WHEN units_sold > 0 THEN date END) AS last_sale')
            ->get()
            ->keyBy('sku_id');
    }

    /**
     * Stock managed in this app wins; otherwise what the sales channel reports,
     * exactly as the inventory screen shows it.
     *
     * @return Collection<int, \stdClass>
     */
    private function stock(): Collection
    {
        return DB::table('inventory')
            ->where('tenant_id', Tenant::id())
            ->groupBy('sku_id')
            ->selectRaw('sku_id')
            ->selectRaw('MAX(CASE WHEN source = ? THEN available END) AS managed', [StockLedger::SOURCE])
            ->selectRaw('MAX(CASE WHEN source <> ? THEN available END) AS channel', [StockLedger::SOURCE])
            ->selectRaw('COALESCE(MAX(incoming), 0) AS incoming')
            ->get()
            ->keyBy('sku_id');
    }

    /** @return Collection<int, \stdClass> */
    private function skus(): Collection
    {
        return DB::table('skus as s')
            ->leftJoin('products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('suppliers as sup', 'sup.id', '=', 's.supplier_id')
            ->where('s.tenant_id', Tenant::id())
            ->where('s.is_active', true)
            ->selectRaw('s.id, s.sku_code, s.name, s.variant_title, s.category, s.brand, s.image_url')
            ->selectRaw('s.cost_price, s.selling_price, s.is_combo, s.tracks_inventory')
            ->selectRaw("COALESCE(NULLIF(p.product_type, ''), NULLIF(s.category, ''), 'Uncategorised') AS type")
            ->selectRaw('p.published_at, p.status AS product_status, sup.name AS supplier_name')
            ->selectRaw('COALESCE(s.created_at, p.created_at) AS added_at')
            ->orderBy('s.sku_code')
            ->get();
    }

    /**
     * @param  Collection<int, \stdClass>  $skus
     * @param  Collection<int, \stdClass>  $sales
     * @param  Collection<int, \stdClass>  $stock
     * @param  RestockSettings  $s
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $skus, Collection $sales, Collection $stock, CarbonImmutable $anchor, int $weff, array $s): array
    {
        // Projection splits an expected orders/day across SKUs by their share of
        // recent demand, so a growth plan lands on the products actually selling.
        $windowUnits = 0;
        foreach ($skus as $sku) {
            if ($this->isExcluded($sku, $s)) {
                continue;
            }
            $windowUnits += max(0, (int) ($sales->get($sku->id)->units_w ?? 0));
        }

        $rows = [];

        foreach ($skus as $sku) {
            $sale = $sales->get($sku->id);
            $st = $stock->get($sku->id);

            $units = max(0, (int) ($sale->units_w ?? 0));
            $onHand = (int) ($st->managed ?? $st->channel ?? 0);
            $incoming = (int) ($st->incoming ?? 0);
            $lastSale = ($sale->last_sale ?? null) !== null
                ? CarbonImmutable::parse((string) $sale->last_sale, Tenant::timezone())
                : null;
            $daysSince = $lastSale?->diffInDays($anchor);
            $daysSince = $daysSince === null ? null : (int) $daysSince;

            $cost = (int) $sku->cost_price > 0 ? (int) $sku->cost_price : $this->fallbackCost($sku, $s);
            $estimatedCost = (int) $sku->cost_price <= 0;

            $velocity = $weff > 0 ? $units / $weff : 0.0;
            $adjusted = false;

            // A SKU that spent half the window at zero stock did not sell slowly;
            // it had nothing to sell. Rate it over the days it was available.
            if ($onHand <= 0 && $units >= 3 && $daysSince !== null) {
                $available = max(7, $weff - $daysSince);
                if ($available < $weff) {
                    $velocity = $units / $available;
                    $adjusted = true;
                }
            }

            $projected = $windowUnits > 0 && $s['projPerDay'] > 0
                ? $s['projPerDay'] * ($units / $windowUnits)
                : 0.0;
            $projectionDrives = $projected > $velocity;
            $velocity = max($velocity, $projected);

            $value = $onHand > 0 ? $onHand * $cost : 0;
            $cover = $onHand > 0 ? ($velocity > 0 ? $onHand / $velocity : null) : 0.0;
            $ageDays = $this->ageDays($sku, $anchor);

            [$bucket, $blocked, $excess] = $this->classify($sku, $s, $onHand, $velocity, $cover, $daysSince, $value, $cost, $ageDays);

            // A virtual combo holds no stock of its own — counting its value would
            // double money already counted on the SKUs inside it.
            if ($bucket === 'excluded') {
                $value = 0;
            }

            $suggested = 0;
            $orderValue = 0;

            if (($bucket === 'reorder' || $bucket === 'soon') && $velocity > 0) {
                $need = (int) ceil($s['target'] * $velocity);
                if ($projected > 0) {
                    $need = max($need, (int) ceil(30 * $projected));
                }

                $gap = $need - max($onHand, 0) - $incoming;
                if ($gap > 0) {
                    $suggested = (int) (ceil($gap / $s['round']) * $s['round']);
                    $orderValue = $suggested * $cost;
                }
            }

            $rows[] = [
                'bucket' => $bucket,
                'units' => $units,
                'revenue' => max(0, (int) ($sale->revenue_w ?? 0)),
                'bestUnits' => max(0, (int) ($sale->units_best ?? 0)),
                'value' => $value,
                'blocked' => $blocked,
                'orderValue' => $orderValue,
                'out' => [
                    'sku_id' => (int) $sku->id,
                    'sku_code' => (string) $sku->sku_code,
                    'name' => (string) $sku->name,
                    'variant_title' => $sku->variant_title,
                    'type' => (string) $sku->type,
                    'supplier_name' => $sku->supplier_name,
                    'image_url' => $sku->image_url,
                    'stock' => $onHand,
                    'incoming' => $incoming,
                    'units_window' => $units,
                    'units_30' => max(0, (int) ($sale->units_30 ?? 0)),
                    'units_prev_30' => max(0, (int) ($sale->units_prev_30 ?? 0)),
                    'returns_window' => max(0, (int) ($sale->returns_w ?? 0)),
                    'revenue_window' => max(0, (int) ($sale->revenue_w ?? 0)),
                    'velocity' => round($velocity, 3),
                    'projected_velocity' => round($projected, 3),
                    'projection_drives' => $projectionDrives && $projected > 0,
                    'velocity_adjusted' => $adjusted,
                    'low_confidence' => $units > 0 && $units < 5,
                    'cover_days' => $cover === null ? null : round($cover, 1),
                    'days_since_sale' => $daysSince,
                    'age_days' => $ageDays,
                    'bucket' => $bucket,
                    'suggested_qty' => $suggested,
                    'order_value' => $orderValue,
                    'unit_cost' => $cost,
                    'cost_estimated' => $estimatedCost,
                    'stock_value' => $value,
                    'blocked_value' => $blocked,
                    'excess_units' => $excess,
                    'abc' => 'C',
                    'bestseller_rank' => null,
                    'bestseller_units' => max(0, (int) ($sale->units_best ?? 0)),
                    'is_top_seller' => false,
                ],
            ];
        }

        return $rows;
    }

    /**
     * @param  RestockSettings  $s
     * @return array{0: string, 1: int, 2: int}
     */
    private function classify(\stdClass $sku, array $s, int $onHand, float $velocity, ?float $cover, ?int $daysSince, int $value, int $cost, ?int $ageDays): array
    {
        if ($this->isExcluded($sku, $s)) {
            return ['excluded', 0, 0];
        }

        if ($onHand <= 0) {
            return [$velocity > 0 ? 'reorder' : 'inactive', 0, 0];
        }

        if ($daysSince === null || $daysSince > $s['dead']) {
            // A product added last week has not gone dead; it has not started.
            $isNew = $daysSince === null && $s['newDays'] > 0 && $ageDays !== null && $ageDays <= $s['newDays'];

            return $isNew ? ['new', 0, 0] : ['dead', $value, 0];
        }

        if ($cover === null) {
            return ['healthy', 0, 0];
        }

        if ($cover <= $s['lead'] + $s['safety']) {
            return ['reorder', 0, 0];
        }

        if ($cover <= $s['lead'] + $s['safety'] + 14) {
            return ['soon', 0, 0];
        }

        if ($cover <= $s['over']) {
            return ['healthy', 0, 0];
        }

        $excess = max(0, $onHand - (int) ceil($s['target'] * $velocity));

        return ['overstock', $excess * $cost, $excess];
    }

    /** @param RestockSettings $s */
    private function isExcluded(\stdClass $sku, array $s): bool
    {
        if ($s['exclude'] === []) {
            return false;
        }

        $haystack = strtolower(implode(' ', array_filter([$sku->name, $sku->variant_title, $sku->type])));

        foreach ($s['exclude'] as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /** @param RestockSettings $s */
    private function fallbackCost(\stdClass $sku, array $s): int
    {
        return $s['costMode'] === 'percent'
            ? (int) round((int) $sku->selling_price * $s['costValue'] / 100)
            : (int) round($s['costValue'] * 100);
    }

    private function ageDays(\stdClass $sku, CarbonImmutable $anchor): ?int
    {
        $added = $sku->published_at ?? $sku->added_at ?? null;

        if ($added === null) {
            return null;
        }

        return (int) CarbonImmutable::parse((string) $added, Tenant::timezone())->diffInDays($anchor);
    }

    /** @param list<array<string, mixed>> $rows */
    private function rankAbc(array &$rows): void
    {
        $earning = array_values(array_filter($rows, static fn (array $r): bool => $r['revenue'] > 0));
        usort($earning, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        $total = array_sum(array_column($earning, 'revenue'));
        $byId = [];
        $cumulative = 0;

        foreach ($earning as $row) {
            $cumulative += $row['revenue'];
            $byId[$row['out']['sku_id']] = $cumulative <= $total * 0.8 ? 'A' : ($cumulative <= $total * 0.95 ? 'B' : 'C');
        }

        foreach ($rows as &$row) {
            $row['out']['abc'] = $byId[$row['out']['sku_id']] ?? 'C';
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function rankBestsellers(array &$rows): void
    {
        $selling = array_values(array_filter($rows, static fn (array $r): bool => $r['bestUnits'] > 0));
        usort($selling, static fn (array $a, array $b): int => $b['bestUnits'] <=> $a['bestUnits']);

        $ranks = [];
        foreach ($selling as $i => $row) {
            $ranks[$row['out']['sku_id']] = $i + 1;
        }

        foreach ($rows as &$row) {
            $rank = $ranks[$row['out']['sku_id']] ?? null;
            $row['out']['bestseller_rank'] = $rank;
            $row['out']['is_top_seller'] = $rank !== null && $rank <= 300;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  RestockSettings  $s
     * @return array<string, mixed>
     */
    private function summary(array $rows, array $s): array
    {
        $value = 0;
        $dead = 0;
        $excess = 0;
        $spendNow = 0;
        $spendSoon = 0;
        $outCount = 0;
        $holding = 0;

        foreach ($rows as $row) {
            $value += $row['value'];
            $holding += $row['out']['stock'] > 0 && $row['bucket'] !== 'excluded' ? 1 : 0;

            match ($row['bucket']) {
                'dead' => $dead += $row['blocked'],
                'overstock' => $excess += $row['blocked'],
                'reorder' => $spendNow += $row['orderValue'],
                'soon' => $spendSoon += $row['orderValue'],
                default => null,
            };

            if ($row['bucket'] === 'reorder' && $row['out']['stock'] <= 0) {
                $outCount++;
            }
        }

        return [
            'stock_value' => $value,
            'working_value' => max(0, $value - $dead - $excess),
            'dead_value' => $dead,
            'excess_value' => $excess,
            'releasable_value' => $dead + $excess,
            'spend_now' => $spendNow,
            'spend_soon' => $spendSoon,
            'out_of_stock_sellers' => $outCount,
            'skus_holding_stock' => $holding,
            'dead_after_days' => $s['dead'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function buckets(array $rows): array
    {
        $agg = [];
        foreach (self::BUCKET_ORDER as $bucket) {
            $agg[$bucket] = ['bucket' => $bucket, 'skus' => 0, 'stock_value' => 0, 'blocked_value' => 0, 'order_value' => 0];
        }

        foreach ($rows as $row) {
            $b = &$agg[$row['bucket']];
            $b['skus']++;
            $b['stock_value'] += $row['value'];
            $b['blocked_value'] += $row['blocked'];
            $b['order_value'] += $row['orderValue'];
        }

        return array_values($agg);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function categories(array $rows): array
    {
        $byType = [];
        $totalUnits = 0;

        foreach ($rows as $row) {
            $type = $row['out']['type'];
            $byType[$type] ??= ['type' => $type, 'skus_in_stock' => 0, 'stock_value' => 0, 'blocked_value' => 0, 'units' => 0];
            $byType[$type]['skus_in_stock'] += $row['out']['stock'] > 0 ? 1 : 0;
            $byType[$type]['stock_value'] += $row['value'];
            $byType[$type]['blocked_value'] += $row['blocked'];
            $byType[$type]['units'] += $row['units'];
            $totalUnits += $row['units'];
        }

        $rowsOut = array_values(array_filter(
            $byType,
            static fn (array $c): bool => $c['stock_value'] > 0 || $c['units'] > 0,
        ));

        usort($rowsOut, static fn (array $a, array $b): int => $b['blocked_value'] <=> $a['blocked_value']);

        return array_map(static function (array $c) use ($totalUnits): array {
            $c['sales_share_pct'] = Num::pct($c['units'], $totalUnits);

            return $c;
        }, array_slice($rowsOut, 0, 12));
    }

    /**
     * Honest notes about what the numbers can and cannot carry today.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  RestockSettings  $s
     * @return list<array{level: string, text: string}>
     */
    private function health(array $rows, CarbonImmutable $anchor, int $span, int $weff, array $s): array
    {
        $notes = [];
        $value = 0;
        $estimated = 0;
        $adjusted = 0;
        $lowConfidence = 0;
        $negative = 0;
        $excluded = 0;

        foreach ($rows as $row) {
            $value += $row['value'];
            $estimated += $row['out']['cost_estimated'] ? $row['value'] : 0;
            $adjusted += $row['out']['velocity_adjusted'] ? 1 : 0;
            $lowConfidence += $row['out']['low_confidence'] ? 1 : 0;
            $negative += $row['out']['stock'] < 0 ? 1 : 0;
            $excluded += $row['bucket'] === 'excluded' ? 1 : 0;
        }

        $estPct = Num::pct($estimated, $value);
        $notes[] = $estPct <= 10
            ? ['level' => 'ok', 'text' => sprintf('Real cost known for %s%% of stock value.', round(100 - $estPct))]
            : ['level' => $estPct > 50 ? 'bad' : 'warn', 'text' => sprintf(
                'Cost missing for %s%% of stock value — estimated at %s. Fill cost price in Catalog to make every ₹ figure exact.',
                round($estPct),
                $s['costMode'] === 'percent' ? $s['costValue'].'% of price' : '₹'.$s['costValue'].'/unit',
            )];

        $notes[] = $weff < $s['window']
            ? ['level' => 'warn', 'text' => sprintf('Only %d days of sales history — the %d-day window was reduced to fit. Backfill more orders for steadier velocities.', $span, $s['window'])]
            : ['level' => 'ok', 'text' => sprintf('%d-day sales window fully covered by %d days of history.', $s['window'], $span)];

        $lag = (int) $anchor->diffInDays(CarbonImmutable::now(Tenant::timezone()));
        $notes[] = $lag <= 1
            ? ['level' => 'ok', 'text' => 'Sales data is current.']
            : ['level' => $lag > 7 ? 'bad' : 'warn', 'text' => sprintf('Sales data ends %d days ago — run a sync before acting on these quantities.', $lag)];

        if ($s['projPerDay'] > 0) {
            $notes[] = ['level' => 'ok', 'text' => sprintf('Projection on: %s orders/day split across SKUs by their share of recent sales. Reorder uses whichever is higher — real pace or projection.', $s['projPerDay'])];
        }

        if (! $s['netReturns']) {
            $notes[] = ['level' => 'warn', 'text' => 'Returns and RTO are being counted as sales — velocities read high. Switch to net for honest numbers.'];
        }

        if ($adjusted > 0) {
            $notes[] = ['level' => 'ok', 'text' => sprintf('%d stocked-out sellers use stockout-adjusted velocity, so they are not under-ordered.', $adjusted)];
        }

        if ($lowConfidence > 0) {
            $notes[] = ['level' => 'warn', 'text' => sprintf('%d SKUs sold under 5 units in the window — treat their suggested quantity as a judgement call.', $lowConfidence)];
        }

        if ($negative > 0) {
            $notes[] = ['level' => 'warn', 'text' => sprintf('%d SKUs show negative stock — fix those adjustments before ordering against them.', $negative)];
        }

        if ($excluded > 0) {
            $notes[] = ['level' => 'ok', 'text' => sprintf('%d combo SKUs excluded from every ₹ and reorder figure (keywords: %s).', $excluded, implode(', ', $s['exclude']))];
        }

        return $notes;
    }
}
