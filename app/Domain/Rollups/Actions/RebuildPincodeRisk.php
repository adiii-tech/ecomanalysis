<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Pincode RTO risk, exposed to the storefront as a checkout API so risky COD
 * orders can be nudged to prepaid before they are placed.
 *
 * Pincodes with fewer than 5 shipments stay 'low' — with that little history a
 * risk score would be noise, and we would rather say nothing than fake it.
 */
class RebuildPincodeRisk
{
    private const MIN_SHIPMENTS = 5;

    public function handle(Tenant $tenant): int
    {
        DB::table('pincode_risk')->where('tenant_id', $tenant->id)->delete();

        DB::insert(<<<'SQL'
            INSERT INTO pincode_risk (
                tenant_id, pincode, city, state, shipments_count, rto_count, rto_rate,
                risk_score, risk_band, cod_serviceable, computed_at, created_at, updated_at
            )
            SELECT
                o.tenant_id,
                o.shipping_pincode,
                MAX(o.shipping_city),
                MAX(o.shipping_state),
                COUNT(*) AS shipments_count,
                SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END) AS rto_count,
                ROUND(SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 3) AS rto_rate,
                CASE
                    WHEN COUNT(*) < ? THEN 0
                    ELSE LEAST(100, ROUND(SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100 * 2))
                END AS risk_score,
                CASE
                    WHEN COUNT(*) < ? THEN 'low'
                    WHEN SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END) / COUNT(*) >= 0.35 THEN 'critical'
                    WHEN SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END) / COUNT(*) >= 0.20 THEN 'high'
                    WHEN SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END) / COUNT(*) >= 0.10 THEN 'medium'
                    ELSE 'low'
                END AS risk_band,
                CASE
                    WHEN COUNT(*) >= ? AND SUM(CASE WHEN o.is_rto = 1 THEN 1 ELSE 0 END) / COUNT(*) >= 0.35 THEN 0
                    ELSE 1
                END,
                NOW(), NOW(), NOW()
            FROM orders o
            WHERE o.tenant_id = ?
              AND o.shipping_pincode IS NOT NULL
              AND o.shipping_pincode <> ''
            GROUP BY o.tenant_id, o.shipping_pincode
        SQL, [self::MIN_SHIPMENTS, self::MIN_SHIPMENTS, self::MIN_SHIPMENTS, $tenant->id]);

        return (int) DB::table('pincode_risk')->where('tenant_id', $tenant->id)->count();
    }
}
