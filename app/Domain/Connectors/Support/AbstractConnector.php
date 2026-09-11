<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Support;

use App\Domain\Connectors\Contracts\Connector as ConnectorContract;
use App\Domain\Connectors\DTOs\ConnectionResult;
use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Models\Connector as ConnectorModel;
use App\Support\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

abstract class AbstractConnector implements ConnectorContract
{
    protected ?ConnectorModel $model = null;

    public function bind(ConnectorModel $model): static
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    protected function model(): ConnectorModel
    {
        return $this->model ??= ConnectorModel::query()
            ->where('tenant_id', app(TenantContext::class)->requireId())
            ->where('connector_id', $this->id())
            ->firstOrFail();
    }

    protected function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->model()->credentials, $key, $default);
    }

    public function isStub(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function webhookTopics(): array
    {
        return [];
    }

    /** @return array<string, int> */
    public function syncCadence(): array
    {
        return array_fill_keys($this->syncableEntities(), 60);
    }

    /**
     * Rate-limit aware HTTP client with exponential backoff.
     *
     * @param  array<string, string>  $headers
     */
    protected function http(string $baseUrl, array $headers = [], int $timeout = 30): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->withHeaders($headers)
            ->timeout($timeout)
            ->retry(4, 500, function (\Throwable $e, PendingRequest $request): bool {
                if ($e instanceof RequestException) {
                    return in_array($e->response->status(), [429, 500, 502, 503, 504], true);
                }

                return $e instanceof ConnectionException;
            }, throw: false)
            ->acceptJson();
    }

    /**
     * Identity hash for a metric row that has no external id of its own.
     * Every dimension that makes the row unique goes in; nulls are normalised
     * so a missing breakdown never collides with an empty one.
     *
     * @param  array<string, mixed>  $dimensions
     */
    protected function rowHash(array $dimensions): string
    {
        ksort($dimensions);

        $parts = array_map(
            static fn (mixed $value): string => $value === null ? "\0" : (string) $value,
            $dimensions,
        );

        return md5(implode('|', $parts));
    }

    /**
     * Upsert metric rows keyed on their dimension hash, so a sync window that
     * overlaps a previous one updates rows instead of duplicating them.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dimensionKeys  columns that together identify a row
     * @param  list<string>  $measureKeys  columns to overwrite on conflict
     */
    protected function upsertMetrics(string $table, array $rows, array $dimensionKeys, array $measureKeys): int
    {
        $hashed = array_map(function (array $row) use ($dimensionKeys): array {
            $row['row_hash'] = $this->rowHash(
                array_combine($dimensionKeys, array_map(
                    static fn (string $key): mixed => $row[$key] ?? null,
                    $dimensionKeys,
                )),
            );

            return $row;
        }, $rows);

        return $this->upsert($table, $hashed, ['tenant_id', 'row_hash'], [...$measureKeys, 'updated_at']);
    }

    /**
     * Idempotent upsert on (tenant_id, source, external_id).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     * @param  list<string>  $update
     */
    protected function upsert(string $table, array $rows, array $uniqueBy, array $update): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $rows = array_map(static function (array $row) use ($now): array {
            $row['created_at'] ??= $now;
            $row['updated_at'] = $now;

            return $row;
        }, $rows);

        $affected = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $affected += DB::table($table)->upsert($chunk, $uniqueBy, $update);
        }

        return count($rows);
    }

    /**
     * Stock a channel reports has no location, and MySQL never treats two NULLs
     * as equal — so an upsert keyed on location inserted a fresh row on every
     * sync and every SUM over stock multiplied. Rows are matched by SKU instead,
     * and copies an earlier sync left behind are dropped first.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function upsertChannelStock(int $tenantId, string $source, array $rows): int
    {
        DB::statement(<<<'SQL'
            DELETE stale FROM inventory stale
            JOIN inventory newer
              ON newer.tenant_id = stale.tenant_id
             AND newer.sku_id = stale.sku_id
             AND newer.source = stale.source
             AND newer.location_id IS NULL
             AND newer.id > stale.id
            WHERE stale.tenant_id = ? AND stale.source = ? AND stale.location_id IS NULL
        SQL, [$tenantId, $source]);

        $existing = DB::table('inventory')
            ->where('tenant_id', $tenantId)
            ->where('source', $source)
            ->whereNull('location_id')
            ->pluck('id', 'sku_id');

        $rows = array_map(static fn (array $row): array => ['id' => $existing[$row['sku_id']] ?? null, ...$row], $rows);

        return $this->upsert('inventory', $rows, ['id'], ['on_hand', 'reserved', 'available', 'synced_at', 'updated_at']);
    }

    /**
     * Default: a connector that only stores credentials and verifies them.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function connect(array $credentials): ConnectionResult
    {
        $missing = [];
        foreach ($this->credentialFields() as $field => $config) {
            if (($config['required'] ?? false) && blank($credentials[$field] ?? null)) {
                $missing[] = $config['label'];
            }
        }

        if ($missing !== []) {
            return ConnectionResult::failure('Missing required fields: '.implode(', ', $missing).'.');
        }

        return ConnectionResult::success(
            $credentials['account_label'] ?? $this->label(),
            $credentials,
        );
    }

    public function testConnection(): HealthResult
    {
        return HealthResult::ok('Credentials stored. No live health probe implemented for this connector yet.');
    }

    public function sync(SyncContext $ctx): SyncReport
    {
        $method = 'sync'.str($ctx->entity)->studly()->toString();

        if (! method_exists($this, $method)) {
            return SyncReport::failed($ctx->entity, "Entity [{$ctx->entity}] is not supported by {$this->label()}.");
        }

        return $this->{$method}($ctx);
    }
}
