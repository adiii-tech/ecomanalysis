<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Actions;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RebuildStateRollup
{
    public function handle(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $offset = CarbonImmutable::now($tenant->timezone)->format('P');
        $localDate = "DATE(CONVERT_TZ(o.placed_at, '+00:00', '{$offset}'))";
        $fromUtc = $from->startOfDay()->setTimezone('UTC')->toDateTimeString();
        $toUtc = $to->endOfDay()->setTimezone('UTC')->toDateTimeString();

        DB::table('state_daily_rollup')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->delete();

        DB::insert(<<<SQL
            INSERT INTO state_daily_rollup (
                tenant_id, date, state, channel_id, orders_count, gross_sales, net_sales, margin,
                rto_count, returned_count, delivered_count, cod_orders, computed_at, created_at, updated_at
            )
            SELECT
                o.tenant_id,
                {$localDate} AS d,
                COALESCE(NULLIF(o.shipping_state, ''), 'Unknown') AS st,
                o.channel_id,
                COUNT(*),
                COALESCE(SUM(o.gross_amount), 0),
                COALESCE(SUM(o.net_amount), 0),
                COALESCE(SUM(o.contribution_margin), 0),
                SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END),
                SUM(CASE WHEN o.returned_amount > 0 THEN 1 ELSE 0 END),
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END),
                SUM(CASE WHEN o.payment_mode = 'cod' THEN 1 ELSE 0 END),
                NOW(), NOW(), NOW()
            FROM orders o
            WHERE o.tenant_id = ?
              AND o.placed_at BETWEEN ? AND ?
            GROUP BY o.tenant_id, d, st, o.channel_id
        SQL, [$tenant->id, $fromUtc, $toUtc]);

        return DB::table('state_daily_rollup')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }
}
