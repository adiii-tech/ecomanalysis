<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Actions;

use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Enums\ConnectorStatus;
use App\Enums\SyncStatus;
use App\Models\Connector;
use App\Models\SyncRun;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one connector entity sync, recording a sync_runs row either way so the
 * UI can show honest sync health rather than a silent failure.
 */
class RunConnectorSync
{
    public function __construct(private readonly ConnectorRegistry $registry) {}

    public function handle(
        Tenant $tenant,
        string $connectorId,
        string $entity,
        string $trigger = 'scheduled',
        bool $backfill = false,
        ?CarbonImmutable $since = null,
        ?CarbonImmutable $until = null,
    ): SyncReport {
        $model = Connector::query()
            ->where('tenant_id', $tenant->id)
            ->where('connector_id', $connectorId)
            ->first();

        if ($model === null || ! $model->isConnected()) {
            return SyncReport::failed($entity, "Connector [{$connectorId}] is not connected.");
        }

        $driver = $this->registry->make($connectorId);
        if (method_exists($driver, 'bind')) {
            $driver = $driver->bind($model);
        }

        $cursorBefore = $model->cursorFor($entity);

        $run = SyncRun::query()->create([
            'tenant_id' => $tenant->id,
            'connector_id' => $connectorId,
            'entity' => $entity,
            'status' => SyncStatus::Running,
            'trigger' => $trigger,
            'started_at' => now(),
            'cursor_before' => ['cursor' => $cursorBefore],
        ]);

        $model->forceFill(['status' => ConnectorStatus::Syncing])->save();
        $startedAt = hrtime(true);

        try {
            $report = $driver->sync(new SyncContext(
                tenant: $tenant,
                connector: $model,
                entity: $entity,
                cursor: $backfill ? null : $cursorBefore,
                since: $since,
                until: $until,
                backfill: $backfill,
                trigger: $trigger,
            ));
        } catch (Throwable $e) {
            Log::error('Connector sync failed', [
                'tenant' => $tenant->id, 'connector' => $connectorId, 'entity' => $entity, 'error' => $e->getMessage(),
            ]);
            $report = SyncReport::failed($entity, $e->getMessage());
        }

        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        $run->forceFill([
            'status' => $report->ok() ? SyncStatus::Success : SyncStatus::Failed,
            'finished_at' => now(),
            'records_fetched' => $report->fetched,
            'records_upserted' => $report->upserted,
            'duration_ms' => $durationMs,
            'error' => $report->error,
            'cursor_after' => ['cursor' => $report->cursorAfter],
        ])->save();

        if ($report->ok()) {
            // A bounded window says nothing about what changed after it, so it must not move the cursor.
            if ($until === null) {
                $model->setCursorFor($entity, $report->cursorAfter);
            }

            $model->forceFill([
                'status' => ConnectorStatus::Connected,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } else {
            $model->forceFill([
                'status' => ConnectorStatus::Error,
                'last_error' => $report->error,
            ])->save();
        }

        return $report;
    }
}
