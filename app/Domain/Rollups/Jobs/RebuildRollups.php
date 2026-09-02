<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Jobs;

use App\Domain\Rollups\Actions\RebuildAdSpendRollup;
use App\Domain\Rollups\Actions\RebuildCohorts;
use App\Domain\Rollups\Actions\RebuildCustomerMetrics;
use App\Domain\Rollups\Actions\RebuildDailyMetrics;
use App\Domain\Rollups\Actions\RebuildPincodeRisk;
use App\Domain\Rollups\Actions\RebuildSkuRollup;
use App\Domain\Rollups\Actions\RebuildStateRollup;
use App\Models\Tenant;
use App\Support\MetricCache;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds every rollup for a tenant, then busts the dashboard cache so the
 * next request sees fresh numbers. Runs after a sync completes and nightly for
 * a full recompute.
 */
class RebuildRollups implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $tenantId,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly bool $full = false,
    ) {
        $this->onQueue('rollups');
    }

    public function handle(
        TenantContext $context,
        RebuildDailyMetrics $daily,
        RebuildSkuRollup $sku,
        RebuildStateRollup $state,
        RebuildAdSpendRollup $adSpend,
        RebuildCustomerMetrics $customers,
        RebuildCohorts $cohorts,
        RebuildPincodeRisk $pincodes,
        MetricCache $cache,
    ): void {
        $tenant = Tenant::query()->findOrFail($this->tenantId);

        $context->runAs($tenant, function () use (
            $tenant, $daily, $sku, $state, $adSpend, $customers, $cohorts, $pincodes, $cache,
        ): void {
            $to = CarbonImmutable::parse($this->to ?? 'now', $tenant->timezone)->endOfDay();
            $from = $this->from !== null
                ? CarbonImmutable::parse($this->from, $tenant->timezone)->startOfDay()
                : $to->subDays($this->full ? 730 : 90)->startOfDay();

            $counts = [
                'daily' => $daily->handle($tenant, $from, $to),
                'sku' => $sku->handle($tenant, $from, $to),
                'state' => $state->handle($tenant, $from, $to),
                'ad_spend' => $adSpend->handle($tenant, $from, $to),
                'customers' => $customers->handle($tenant),
                'cohorts' => $cohorts->handle($tenant),
                'pincodes' => $pincodes->handle($tenant),
            ];

            $cache->bust($tenant->id);

            Log::info('Rollups rebuilt', ['tenant' => $tenant->id, 'window' => [$from->toDateString(), $to->toDateString()], ...$counts]);
        });
    }

    public function uniqueId(): string
    {
        return (string) $this->tenantId;
    }
}
