<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Acquisition-month cohorts: for each cohort, how many of its customers were
 * still buying in month 0..12, and what revenue/margin they produced.
 */
class RebuildCohorts
{
    public function handle(Tenant $tenant, int $months = 12): int
    {
        $offset = now($tenant->timezone)->format('P');

        DB::table('cohort_snapshots')->where('tenant_id', $tenant->id)->delete();

        DB::insert(<<<SQL
            INSERT INTO cohort_snapshots (
                tenant_id, cohort_month, month_index, cohort_size, active_customers,
                retention_pct, revenue, margin, cumulative_revenue, avg_ltv,
                computed_at, created_at, updated_at
            )
            SELECT
                b.tenant_id,
                b.cohort_month,
                b.month_index,
                sizes.cohort_size,
                b.active_customers,
                ROUND(b.active_customers / NULLIF(sizes.cohort_size, 0) * 100, 3),
                b.revenue,
                b.margin,
                b.revenue,
                CASE WHEN sizes.cohort_size > 0 THEN ROUND(b.revenue / sizes.cohort_size) ELSE 0 END,
                NOW(), NOW(), NOW()
            FROM (
                SELECT
                    o.tenant_id,
                    DATE_FORMAT(CONVERT_TZ(c.first_order_at, '+00:00', '{$offset}'), '%Y-%m') AS cohort_month,
                    TIMESTAMPDIFF(
                        MONTH,
                        DATE_FORMAT(CONVERT_TZ(c.first_order_at, '+00:00', '{$offset}'), '%Y-%m-01'),
                        DATE_FORMAT(CONVERT_TZ(o.placed_at, '+00:00', '{$offset}'), '%Y-%m-01')
                    ) AS month_index,
                    COUNT(DISTINCT o.customer_id) AS active_customers,
                    COALESCE(SUM(o.net_amount), 0) AS revenue,
                    COALESCE(SUM(o.contribution_margin), 0) AS margin
                FROM orders o
                JOIN customers c ON c.id = o.customer_id
                WHERE o.tenant_id = ?
                  AND o.status <> 'cancelled'
                  AND c.first_order_at IS NOT NULL
                GROUP BY o.tenant_id, cohort_month, month_index
                HAVING month_index BETWEEN 0 AND ?
            ) b
            JOIN (
                SELECT
                    DATE_FORMAT(CONVERT_TZ(first_order_at, '+00:00', '{$offset}'), '%Y-%m') AS cohort_month,
                    COUNT(*) AS cohort_size
                FROM customers
                WHERE tenant_id = ? AND first_order_at IS NOT NULL
                GROUP BY cohort_month
            ) sizes ON sizes.cohort_month = b.cohort_month
        SQL, [$tenant->id, $months, $tenant->id]);

        DB::statement(<<<'SQL'
            UPDATE cohort_snapshots cs
            JOIN (
                SELECT cohort_month, month_index,
                       SUM(revenue) OVER (PARTITION BY cohort_month ORDER BY month_index
                                          ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running
                FROM cohort_snapshots
                WHERE tenant_id = ?
            ) r ON r.cohort_month = cs.cohort_month AND r.month_index = cs.month_index
            SET cs.cumulative_revenue = r.running,
                cs.avg_ltv = CASE WHEN cs.cohort_size > 0 THEN ROUND(r.running / cs.cohort_size) ELSE 0 END
            WHERE cs.tenant_id = ?
        SQL, [$tenant->id, $tenant->id]);

        return (int) DB::table('cohort_snapshots')->where('tenant_id', $tenant->id)->count();
    }
}
