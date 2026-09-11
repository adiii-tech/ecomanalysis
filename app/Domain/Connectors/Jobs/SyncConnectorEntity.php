<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Jobs;

use App\Domain\Connectors\Actions\RunConnectorSync;
use App\Domain\Rollups\Jobs\RebuildRollups;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncConnectorEntity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $tenantId,
        public readonly string $connectorId,
        public readonly string $entity,
        public readonly string $trigger = 'scheduled',
        public readonly bool $backfill = false,
        public readonly ?string $since = null,
        public readonly bool $rebuildRollups = true,
        public readonly ?string $until = null,
    ) {
        $this->onQueue('sync-'.$connectorId);
    }

    /** @return list<object> */
    public function middleware(): array
    {
        // A history window must neither block nor wait on the live sync of the same entity.
        $key = "sync:{$this->tenantId}:{$this->connectorId}:{$this->entity}".($this->until !== null ? ':backfill' : '');

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(60)
                ->expireAfter(1800),
            new RateLimited('connector-sync'),
        ];
    }

    public function handle(RunConnectorSync $sync, TenantContext $context): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);

        $context->runAs($tenant, function () use ($sync, $tenant): void {
            $report = $sync->handle(
                $tenant,
                $this->connectorId,
                $this->entity,
                $this->trigger,
                $this->backfill,
                $this->since !== null ? CarbonImmutable::parse($this->since) : null,
                $this->until !== null ? CarbonImmutable::parse($this->until) : null,
            );

            if ($report->ok() && $report->upserted > 0 && $this->rebuildRollups) {
                RebuildRollups::dispatch($tenant->id)->onQueue('rollups');
            }
        });
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->connectorId}:{$this->entity}";
    }
}
