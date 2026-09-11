<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Marks exactly one order per customer — their earliest — as the first order.
 *
 * The flag is also set as each order is upserted, but that is only right when
 * orders arrive oldest first. A sync that pages newest first, or a backfill
 * that brings in older history later, leaves several "first" orders per
 * customer and returning buyers counted as new. Recomputing it here, before the
 * daily rollup reads it, makes the flag independent of arrival order.
 */
class RebuildFirstOrderFlags
{
    public function handle(Tenant $tenant): int
    {
        return DB::affectingStatement(<<<'SQL'
            UPDATE orders o
            JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY customer_id ORDER BY placed_at, id) AS position
                FROM orders
                WHERE tenant_id = ? AND customer_id IS NOT NULL
            ) ranked ON ranked.id = o.id
            SET o.is_first_order = (ranked.position = 1)
            WHERE o.tenant_id = ? AND o.is_first_order <> (ranked.position = 1)
        SQL, [$tenant->id, $tenant->id]);
    }
}
