<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Actions;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RebuildSkuRollup
{
    public function handle(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $offset = CarbonImmutable::now($tenant->timezone)->format('P');
        $localDate = "DATE(CONVERT_TZ(o.placed_at, '+00:00', '{$offset}'))";
        $fromUtc = $from->startOfDay()->setTimezone('UTC')->toDateTimeString();
        $toUtc = $to->endOfDay()->setTimezone('UTC')->toDateTimeString();

        DB::table('sku_daily_rollup')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->delete();

        DB::insert(<<<SQL
            INSERT INTO sku_daily_rollup (
                tenant_id, date, sku_id, channel_id, units_sold, orders_count,
                gross_sales, net_sales, cogs, fees, margin, returned_units, returned_amount,
                computed_at, created_at, updated_at
            )
            SELECT
                oi.tenant_id,
                {$localDate} AS d,
                oi.sku_id,
                o.channel_id,
                COALESCE(SUM(oi.qty), 0),
                COUNT(DISTINCT oi.order_id),
                COALESCE(SUM(oi.line_gross), 0),
                COALESCE(SUM(oi.line_net), 0),
                COALESCE(SUM(oi.cogs_unit * GREATEST(oi.qty - oi.returned_qty, 0)), 0),
                COALESCE(SUM(oi.line_fees), 0),
                COALESCE(SUM(oi.line_margin), 0),
                COALESCE(SUM(oi.returned_qty), 0),
                COALESCE(SUM(oi.returned_qty * oi.unit_price), 0),
                NOW(), NOW(), NOW()
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE oi.tenant_id = ?
              AND oi.sku_id IS NOT NULL
              AND o.placed_at BETWEEN ? AND ?
              AND o.status <> 'cancelled'
            GROUP BY oi.tenant_id, d, oi.sku_id, o.channel_id
        SQL, [$tenant->id, $fromUtc, $toUtc]);

        return DB::table('sku_daily_rollup')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }
}
