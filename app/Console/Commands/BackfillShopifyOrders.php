<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\Drivers\ShopifyConnector;
use App\Domain\Connectors\Jobs\SyncConnectorEntity;
use App\Domain\Rollups\Jobs\RebuildRollups;
use App\Models\Connector;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Pulls a Shopify store's order history from before the first sync.
 *
 * The first sync only reaches back 90 days, and one job walking years of orders
 * would outrun the 15-minute job timeout and restart from nothing. Each window
 * here is its own job, chained so they run one after another; a failed window
 * stops the chain, and re-running with --since resumes from it. Windows never
 * move the incremental cursor, so live syncing carries on while history fills.
 */
class BackfillShopifyOrders extends Command
{
    /** How far back the first sync reached; only history before this needs fetching. */
    private const FIRST_SYNC_DAYS = 90;

    protected $signature = 'shopify:backfill-orders
        {tenant : Tenant id or slug}
        {--since= : Earliest order date to fetch (YYYY-MM-DD); defaults to when the shop opened}
        {--days=14 : Days of orders per job}';

    protected $description = 'Queue the Shopify order history older than the first sync, as a chain of short windows.';

    public function handle(TenantContext $context, ConnectorRegistry $registry): int
    {
        $key = (string) $this->argument('tenant');

        $tenant = $context->withoutScope(fn (): ?Tenant => Tenant::query()
            ->when(
                ctype_digit($key),
                fn ($query) => $query->whereKey((int) $key),
                fn ($query) => $query->where('slug', $key),
            )
            ->first());

        if ($tenant === null) {
            $this->error("No tenant matches [{$key}].");

            return self::FAILURE;
        }

        return $context->runAs($tenant, function () use ($tenant, $registry): int {
            $connector = Connector::query()->where('connector_id', 'shopify')->first();

            if ($connector === null || ! $connector->isConnected()) {
                $this->error("Shopify is not connected for {$tenant->name}.");

                return self::FAILURE;
            }

            $since = $this->resolveSince($registry, $tenant);

            if ($since === null) {
                $this->error('Could not read when the shop opened. Pass --since=YYYY-MM-DD.');

                return self::FAILURE;
            }

            $until = CarbonImmutable::now($tenant->timezone)->subDays(self::FIRST_SYNC_DAYS)->endOfDay();

            if ($since->greaterThanOrEqualTo($until)) {
                $this->info('Nothing to backfill: the first sync already covers that period.');

                return self::SUCCESS;
            }

            $days = max(1, (int) $this->option('days'));
            $jobs = [];

            for ($start = $since; $start->lessThan($until); $start = $start->addDays($days)) {
                $jobs[] = new SyncConnectorEntity(
                    $tenant->id,
                    'shopify',
                    'orders',
                    trigger: 'backfill',
                    backfill: true,
                    since: $start->toIso8601String(),
                    rebuildRollups: false,
                    until: $start->addDays($days)->min($until)->toIso8601String(),
                );
            }

            $windows = count($jobs);

            // Customer metrics and cohorts only mean something once the whole history is in.
            $jobs[] = new RebuildRollups($tenant->id, full: true);

            Bus::chain($jobs)->dispatch();

            $this->info(sprintf('Queued %d windows of orders from %s to %s, then a full rollup rebuild.', $windows, $since->toDateString(), $until->toDateString()));
            $this->line('Each window appears in Sync history as it finishes. If one fails, re-run with --since set to its start date.');

            return self::SUCCESS;
        });
    }

    private function resolveSince(ConnectorRegistry $registry, Tenant $tenant): ?CarbonImmutable
    {
        if (filled($this->option('since'))) {
            return CarbonImmutable::parse((string) $this->option('since'), $tenant->timezone)->startOfDay();
        }

        $driver = $registry->forTenant('shopify');

        try {
            $opened = $driver instanceof ShopifyConnector ? $driver->shopCreatedAt() : null;
        } catch (Throwable) {
            $opened = null;
        }

        return $opened?->setTimezone($tenant->timezone)->startOfDay();
    }
}
