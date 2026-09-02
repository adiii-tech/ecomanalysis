<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Actions;

use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\DTOs\ConnectionResult;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class ConnectConnector
{
    public function __construct(private readonly ConnectorRegistry $registry) {}

    /** @param array<string, mixed> $credentials */
    public function handle(Tenant $tenant, string $connectorId, array $credentials): ConnectionResult
    {
        $driver = $this->registry->make($connectorId);
        $result = $driver->connect($credentials);

        if (! $result->ok) {
            Connector::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'connector_id' => $connectorId],
                ['status' => ConnectorStatus::Error, 'last_error' => $result->error, 'auth_type' => $driver->authType()],
            );

            return $result;
        }

        DB::transaction(function () use ($tenant, $connectorId, $driver, $result): void {
            $model = Connector::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'connector_id' => $connectorId],
                [
                    'status' => ConnectorStatus::Connected,
                    'auth_type' => $driver->authType(),
                    'credentials' => $result->credentials,
                    'account_label' => $result->accountLabel,
                    'has_secret' => $result->credentials !== [],
                    'last_connected_at' => now(),
                    'last_error' => null,
                ],
            );

            activity('connector')
                ->performedOn($model)
                ->withProperties(['connector' => $connectorId, 'account' => $result->accountLabel])
                ->log('connector.connected');
        });

        return $result;
    }
}
