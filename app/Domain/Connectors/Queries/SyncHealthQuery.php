<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Queries;

use App\Domain\Connectors\ConnectorRegistry;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use App\Models\SyncRun;
use App\Support\TenantContext;

/**
 * Powers the header sync indicator and the connector health page: per-connector
 * last-synced time, last error and a single overall traffic light.
 */
class SyncHealthQuery
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ConnectorRegistry $registry,
    ) {}

    /** @return array<string, mixed>|null */
    public function summary(): ?array
    {
        if (! $this->context->has()) {
            return null;
        }

        $connectors = Connector::query()->orderBy('connector_id')->get();

        if ($connectors->isEmpty()) {
            return ['status' => 'grey', 'label' => 'No connectors', 'connectors' => [], 'stale_count' => 0];
        }

        $lastRuns = SyncRun::query()
            ->whereIn('connector_id', $connectors->pluck('connector_id'))
            ->orderByDesc('started_at')
            ->get()
            ->groupBy('connector_id')
            ->map(static fn ($runs) => $runs->first());

        $rows = $connectors->map(function (Connector $connector) use ($lastRuns): array {
            $driver = $this->registry->has($connector->connector_id) ? $this->registry->make($connector->connector_id) : null;
            $lastRun = $lastRuns->get($connector->connector_id);
            $staleAfterMinutes = $driver === null ? 1440 : (max($driver->syncCadence()) * 3);
            $isStale = $connector->last_synced_at === null
                || $connector->last_synced_at->diffInMinutes(now()) > $staleAfterMinutes;

            return [
                'id' => $connector->connector_id,
                'label' => $driver?->label() ?? $connector->connector_id,
                'status' => $connector->status->value,
                'dot' => $connector->status === ConnectorStatus::Connected && $isStale ? 'amber' : $connector->status->dot(),
                'account_label' => $connector->account_label,
                'last_synced_at' => $connector->last_synced_at?->toIso8601String(),
                'last_synced_human' => $connector->last_synced_at?->diffForHumans(),
                'last_error' => $connector->last_error,
                'is_stale' => $isStale,
                'last_run' => $lastRun === null ? null : [
                    'entity' => $lastRun->entity,
                    'status' => $lastRun->status->value,
                    'records' => $lastRun->records_upserted,
                    'duration_ms' => $lastRun->duration_ms,
                ],
            ];
        })->values();

        $hasError = $rows->contains(fn (array $r): bool => $r['dot'] === 'red');
        $hasStale = $rows->contains(fn (array $r): bool => $r['dot'] === 'amber');

        return [
            'status' => $hasError ? 'red' : ($hasStale ? 'amber' : 'green'),
            'label' => $hasError ? 'Sync errors' : ($hasStale ? 'Some data is stale' : 'All connectors healthy'),
            'connectors' => $rows,
            'stale_count' => $rows->where('is_stale', true)->count(),
        ];
    }

    /**
     * Which connectors are missing, so widgets can show an honest caveat
     * instead of an implied-complete number.
     *
     * @param  list<string>  $required
     * @return list<string>
     */
    public function missing(array $required): array
    {
        $connected = Connector::query()
            ->connected()
            ->pluck('connector_id')
            ->all();

        return array_values(array_diff($required, $connected));
    }
}
