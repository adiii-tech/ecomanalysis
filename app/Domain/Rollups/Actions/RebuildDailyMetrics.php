<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Actions;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes `daily_metrics_rollup` from raw orders for a date window.
 *
 * Every dashboard endpoint reads these rows, never raw orders, which is what
 * keeps the p95 under 300 ms. The job is idempotent: it deletes and rewrites
 * the window, so a partial sync can be replayed safely.
 *
 * Returns and RTO in this table are on the **order-date (cohort) basis**. The
 * return-date basis is overlaid at query time from the `returns` table, which
 * is small enough to aggregate live.
 */
class RebuildDailyMetrics
{
    public function handle(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $offset = $this->tzOffset($tenant);
        $fromUtc = $from->startOfDay()->setTimezone('UTC')->toDateTimeString();
        $toUtc = $to->endOfDay()->setTimezone('UTC')->toDateTimeString();

        DB::table('daily_metrics_rollup')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->delete();

        $localDate = "DATE(CONVERT_TZ(o.placed_at, '+00:00', '{$offset}'))";

        $inserted = DB::insert(<<<SQL
            INSERT INTO daily_metrics_rollup (
                tenant_id, date, channel_id, payment_mode,
                orders_count, units_count, items_count, customers_count, new_customers, repeat_customers,
                gross_sales, discounts, cancelled_amount, cancelled_orders, invoiced_sales, invoiced_orders,
                returned_amount, returned_orders, rto_amount, rto_orders, net_sales, tax_amount, shipping_collected,
                cogs, marketplace_fees, logistics_cost, packaging_cost, gateway_fees, return_cost, contribution_margin,
                loss_orders, loss_amount, computed_at, created_at, updated_at
            )
            SELECT
                o.tenant_id,
                {$localDate} AS d,
                o.channel_id,
                o.payment_mode,
                COUNT(*),
                COALESCE(SUM(o.units_count), 0),
                COALESCE(SUM(o.items_count), 0),
                COUNT(DISTINCT o.customer_id),
                COUNT(DISTINCT CASE WHEN o.is_first_order = 1 THEN o.customer_id END),
                COUNT(DISTINCT CASE WHEN o.is_first_order = 0 THEN o.customer_id END),
                COALESCE(SUM(o.gross_amount), 0),
                COALESCE(SUM(o.discount_amount), 0),
                COALESCE(SUM(o.cancelled_amount), 0),
                SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END),
                COALESCE(SUM(o.invoiced_amount), 0),
                SUM(CASE WHEN o.invoiced_amount > 0 THEN 1 ELSE 0 END),
                COALESCE(SUM(o.returned_amount), 0),
                SUM(CASE WHEN o.returned_amount > 0 THEN 1 ELSE 0 END),
                COALESCE(SUM(o.rto_amount), 0),
                SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END),
                COALESCE(SUM(o.net_amount), 0),
                COALESCE(SUM(o.tax_amount), 0),
                COALESCE(SUM(o.shipping_amount), 0),
                COALESCE(SUM(o.cogs_amount), 0),
                COALESCE(SUM(o.fees_amount), 0),
                COALESCE(SUM(o.logistics_amount), 0),
                COALESCE(SUM(o.packaging_amount), 0),
                COALESCE(SUM(o.gateway_fee_amount), 0),
                COALESCE(SUM(o.return_cost_amount), 0),
                COALESCE(SUM(o.contribution_margin), 0),
                SUM(CASE WHEN o.contribution_margin < 0 THEN 1 ELSE 0 END),
                COALESCE(SUM(CASE WHEN o.contribution_margin < 0 THEN o.contribution_margin ELSE 0 END), 0),
                NOW(), NOW(), NOW()
            FROM orders o
            WHERE o.tenant_id = ?
              AND o.placed_at BETWEEN ? AND ?
            GROUP BY o.tenant_id, d, o.channel_id, o.payment_mode
        SQL, [$tenant->id, $fromUtc, $toUtc]);

        $this->attachShipmentCounts($tenant, $from, $to, $offset);

        return DB::table('daily_metrics_rollup')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    /**
     * Shipment counts hang off the order's date/channel/payment grain so the
     * logistics KPIs stay on the same rollup as the money.
     */
    private function attachShipmentCounts(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to, string $offset): void
    {
        $localDate = "DATE(CONVERT_TZ(o.placed_at, '+00:00', '{$offset}'))";

        DB::statement(<<<SQL
            UPDATE daily_metrics_rollup r
            JOIN (
                SELECT
                    o.tenant_id,
                    {$localDate} AS d,
                    o.channel_id,
                    o.payment_mode,
                    COUNT(s.id) AS shipments_count,
                    SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
                    SUM(CASE WHEN s.status IN ('manifested','in_transit','out_for_delivery','ndr') THEN 1 ELSE 0 END) AS in_transit_count
                FROM shipments s
                JOIN orders o ON o.id = s.order_id
                WHERE o.tenant_id = ? AND o.placed_at BETWEEN ? AND ?
                GROUP BY o.tenant_id, d, o.channel_id, o.payment_mode
            ) x
              ON x.tenant_id = r.tenant_id
             AND x.d = r.date
             AND (x.channel_id <=> r.channel_id)
             AND x.payment_mode = r.payment_mode
            SET r.shipments_count = x.shipments_count,
                r.delivered_count = x.delivered_count,
                r.in_transit_count = x.in_transit_count
        SQL, [
            $tenant->id,
            $from->startOfDay()->setTimezone('UTC')->toDateTimeString(),
            $to->endOfDay()->setTimezone('UTC')->toDateTimeString(),
        ]);
    }

    private function tzOffset(Tenant $tenant): string
    {
        return CarbonImmutable::now($tenant->timezone)->format('P');
    }
}
