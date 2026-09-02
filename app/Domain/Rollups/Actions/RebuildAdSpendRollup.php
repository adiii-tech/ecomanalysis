<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Actions;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Collapses per-ad daily insights into one row per (date, platform) so blended
 * ROAS and MER read a handful of rows instead of tens of thousands.
 *
 * Only the `total` breakdown is summed — placement/demographic rows repeat the
 * same spend and would double-count it.
 */
class RebuildAdSpendRollup
{
    public function handle(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to): int
    {
        DB::table('ad_spend_rollup')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->delete();

        DB::insert(<<<'SQL'
            INSERT INTO ad_spend_rollup (
                tenant_id, date, platform, spend, impressions, clicks, conversions, conversion_value,
                computed_at, created_at, updated_at
            )
            SELECT
                tenant_id, date, platform,
                COALESCE(SUM(spend), 0),
                COALESCE(SUM(impressions), 0),
                COALESCE(SUM(clicks), 0),
                COALESCE(SUM(conversions), 0),
                COALESCE(SUM(conversion_value), 0),
                NOW(), NOW(), NOW()
            FROM ad_insights_daily
            WHERE tenant_id = ?
              AND breakdown_key = 'total'
              AND date BETWEEN ? AND ?
            GROUP BY tenant_id, date, platform
        SQL, [$tenant->id, $from->toDateString(), $to->toDateString()]);

        return DB::table('ad_spend_rollup')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }
}
