<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\Jobs\SyncConnectorEntity;
use App\Models\Connector;
use App\Models\SyncRun;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Queues whichever connector entities are now due.
 *
 * Runs every five minutes; each entity has its own cadence (orders every 15
 * minutes, ads hourly, inventory every 30, reviews every 6 hours) and is only
 * queued once its cadence has elapsed since the last successful run.
 */
class ScheduleConnectorSyncs extends Command
{
    protected $signature = 'connectors:schedule {--tenant= : Limit to one tenant} {--dry-run : Show what would be queued}';

    protected $description = 'Queue connector syncs that are due';

    public function handle(ConnectorRegistry $registry): int
    {
        $queued = 0;

        Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->whereKey($this->option('tenant')))
            ->each(function (Tenant $tenant) use ($registry, &$queued): void {
                $connectors = Connector::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->connected()
                    ->get();

                foreach ($connectors as $connector) {
                    if (! $registry->has($connector->connector_id)) {
                        continue;
                    }

                    $driver = $registry->make($connector->connector_id);

                    if ($driver->isStub()) {
                        continue;
                    }

                    foreach ($driver->syncCadence() as $entity => $minutes) {
                        if (! $this->isDue($tenant->id, $connector->connector_id, $entity, $minutes)) {
                            continue;
                        }

                        $queued++;
                        $this->line("  queue {$tenant->slug} · {$connector->connector_id} · {$entity}");

                        if (! $this->option('dry-run')) {
                            SyncConnectorEntity::dispatch($tenant->id, $connector->connector_id, $entity);
                        }
                    }
                }
            });

        $this->info($this->option('dry-run') ? "{$queued} syncs due." : "Queued {$queued} syncs.");

        return self::SUCCESS;
    }

    private function isDue(int $tenantId, string $connectorId, string $entity, int $cadenceMinutes): bool
    {
        $lastRun = SyncRun::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('connector_id', $connectorId)
            ->where('entity', $entity)
            // A history backfill says nothing about how fresh the live data is.
            ->where('trigger', '!=', 'backfill')
            ->whereNotNull('started_at')
            ->latest('started_at')
            ->first();

        return $lastRun === null || $lastRun->started_at->addMinutes($cadenceMinutes)->isPast();
    }
}
