<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Actions;

use App\Enums\RfmSegment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes per-customer aggregates (orders, spend, LTV, margin, recency) and
 * the RFM quintile segmentation. D2C only — marketplaces anonymise buyers, so
 * marketplace orders never create a customer row to attach to.
 */
class RebuildCustomerMetrics
{
    public function handle(Tenant $tenant): int
    {
        DB::statement(<<<'SQL'
            UPDATE customers c
            LEFT JOIN (
                SELECT customer_id,
                       COUNT(*) AS orders_count,
                       COALESCE(SUM(net_amount), 0) AS total_spent,
                       COALESCE(SUM(contribution_margin), 0) AS total_margin,
                       MIN(placed_at) AS first_order_at,
                       MAX(placed_at) AS last_order_at
                FROM orders
                WHERE tenant_id = ? AND status <> 'cancelled'
                GROUP BY customer_id
            ) o ON o.customer_id = c.id
            SET c.orders_count = COALESCE(o.orders_count, 0),
                c.total_spent = COALESCE(o.total_spent, 0),
                c.total_margin = COALESCE(o.total_margin, 0),
                c.ltv = COALESCE(o.total_spent, 0),
                c.aov = CASE WHEN COALESCE(o.orders_count, 0) > 0 THEN ROUND(o.total_spent / o.orders_count) ELSE 0 END,
                c.first_order_at = o.first_order_at,
                c.last_order_at = o.last_order_at,
                c.days_since_last_order = CASE WHEN o.last_order_at IS NULL THEN NULL ELSE DATEDIFF(NOW(), o.last_order_at) END,
                c.avg_days_between_orders = CASE
                    WHEN COALESCE(o.orders_count, 0) > 1
                    THEN ROUND(DATEDIFF(o.last_order_at, o.first_order_at) / (o.orders_count - 1))
                    ELSE NULL END
            WHERE c.tenant_id = ?
        SQL, [$tenant->id, $tenant->id]);

        DB::statement(<<<'SQL'
            UPDATE customers c
            LEFT JOIN (
                SELECT o.customer_id, COUNT(*) AS returns_count
                FROM returns r
                JOIN orders o ON o.id = r.order_id
                WHERE r.tenant_id = ?
                GROUP BY o.customer_id
            ) x ON x.customer_id = c.id
            SET c.returns_count = COALESCE(x.returns_count, 0)
            WHERE c.tenant_id = ?
        SQL, [$tenant->id, $tenant->id]);

        $this->scoreRfm($tenant);
        $this->scoreChurn($tenant);

        return (int) DB::table('customers')->where('tenant_id', $tenant->id)->count();
    }

    /**
     * Quintile scoring: recency reversed (recent = 5), frequency and monetary
     * ascending. NTILE keeps it distribution-relative rather than using
     * arbitrary absolute thresholds that would be wrong for most brands.
     */
    private function scoreRfm(Tenant $tenant): void
    {
        DB::statement(<<<'SQL'
            UPDATE customers c
            JOIN (
                SELECT id,
                       6 - NTILE(5) OVER (ORDER BY COALESCE(days_since_last_order, 99999) ASC) AS r,
                       NTILE(5) OVER (ORDER BY orders_count ASC) AS f,
                       NTILE(5) OVER (ORDER BY total_spent ASC) AS m
                FROM customers
                WHERE tenant_id = ? AND orders_count > 0
            ) s ON s.id = c.id
            SET c.rfm_r = s.r, c.rfm_f = s.f, c.rfm_m = s.m
            WHERE c.tenant_id = ?
        SQL, [$tenant->id, $tenant->id]);

        $segments = [
            RfmSegment::Champions->value => 'c.rfm_r >= 4 AND c.rfm_f >= 4 AND c.rfm_m >= 4',
            RfmSegment::Loyal->value => 'c.rfm_r >= 3 AND c.rfm_f >= 3',
            RfmSegment::Potential->value => 'c.rfm_r >= 4 AND c.rfm_f <= 2',
            RfmSegment::New->value => 'c.rfm_r = 5 AND c.orders_count = 1',
            RfmSegment::AtRisk->value => 'c.rfm_r = 2 AND c.rfm_f >= 3',
            RfmSegment::Hibernating->value => 'c.rfm_r = 2',
            RfmSegment::Lost->value => 'c.rfm_r = 1',
        ];

        foreach ($segments as $segment => $condition) {
            DB::statement(
                "UPDATE customers c SET c.rfm_segment = ? WHERE c.tenant_id = ? AND c.rfm_segment IS NULL AND {$condition}",
                [$segment, $tenant->id],
            );
        }

        DB::table('customers')
            ->where('tenant_id', $tenant->id)
            ->whereNull('rfm_segment')
            ->where('orders_count', '>', 0)
            ->update(['rfm_segment' => RfmSegment::Potential->value]);

        DB::statement(
            'UPDATE customers SET is_vip = (rfm_segment = ? AND total_spent > 0) WHERE tenant_id = ?',
            [RfmSegment::Champions->value, $tenant->id],
        );
    }

    /**
     * Churn risk is recency measured against the customer's own cadence — a
     * subscriber who orders monthly is at risk at 60 days; an annual buyer is not.
     */
    private function scoreChurn(Tenant $tenant): void
    {
        DB::statement(<<<'SQL'
            UPDATE customers
            SET churn_risk_score = LEAST(100, GREATEST(0, ROUND(
                CASE
                    WHEN orders_count = 0 OR days_since_last_order IS NULL THEN 0
                    WHEN avg_days_between_orders IS NULL THEN LEAST(100, days_since_last_order / 1.2)
                    ELSE (days_since_last_order / GREATEST(avg_days_between_orders, 1)) * 40
                END
            )))
            WHERE tenant_id = ?
        SQL, [$tenant->id]);
    }
}
