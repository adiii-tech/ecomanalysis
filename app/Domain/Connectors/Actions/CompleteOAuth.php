<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Actions;

use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\Contracts\SupportsOAuth;
use App\Domain\Connectors\DTOs\ConnectionResult;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Throwable;

class CompleteOAuth
{
    public function __construct(private readonly ConnectorRegistry $registry) {}

    /**
     * Store the credentials an OAuth exchange produced.
     *
     * A connector only reaches `connected` once every pending selection is
     * satisfied; until then it sits in `needs_setup` so nothing tries to sync
     * against a token with no account attached.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $context
     */
    public function handle(Tenant $tenant, string $connectorId, array $query, string $redirectUri, array $context = []): ConnectionResult
    {
        $driver = $this->registry->make($connectorId);

        if (! $driver instanceof SupportsOAuth) {
            return ConnectionResult::failure('That connector does not authorise by redirect.');
        }

        try {
            $result = $driver->exchangeCode($query, $redirectUri, $context);
        } catch (Throwable $e) {
            $result = ConnectionResult::failure('Token exchange failed: '.$e->getMessage());
        }

        if (! $result->ok) {
            Connector::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'connector_id' => $connectorId],
                ['status' => ConnectorStatus::Error, 'last_error' => $result->error, 'auth_type' => $driver->authType()],
            );

            return $result;
        }

        $model = DB::transaction(function () use ($tenant, $connectorId, $driver, $result): Connector {
            $existing = Connector::query()
                ->where('tenant_id', $tenant->id)
                ->where('connector_id', $connectorId)
                ->first();

            // Re-authorising must not wipe selections the merchant already made.
            $credentials = [...($existing?->credentials ?? []), ...$result->credentials];

            $model = Connector::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'connector_id' => $connectorId],
                [
                    'status' => ConnectorStatus::NeedsSetup,
                    'auth_type' => $driver->authType(),
                    'credentials' => $credentials,
                    'account_label' => $result->accountLabel,
                    'has_secret' => true,
                    'last_connected_at' => now(),
                    'last_error' => null,
                ],
            );

            activity('connector')
                ->performedOn($model)
                ->withProperties(['connector' => $connectorId, 'account' => $result->accountLabel, 'via' => 'oauth'])
                ->log('connector.authorised');

            return $model;
        });

        $this->settleStatus($model, $driver);

        return $result;
    }

    /**
     * Flip to connected once nothing is left to choose, and run the connector's
     * own post-connect work (Shopify registers its webhooks here).
     */
    public function settleStatus(Connector $model, ?SupportsOAuth $driver = null): ConnectorStatus
    {
        $driver ??= $this->registry->make($model->connector_id) instanceof SupportsOAuth
            ? $this->registry->make($model->connector_id)
            : null;

        if ($driver === null) {
            return $model->status;
        }

        $missing = array_filter(
            array_keys($driver->pendingSelections()),
            static fn (string $key): bool => blank(data_get($model->credentials, $key)),
        );

        $status = $missing === [] ? ConnectorStatus::Connected : ConnectorStatus::NeedsSetup;
        $model->forceFill(['status' => $status])->save();

        $driver = $this->registry->forTenant($model->connector_id);

        if ($status === ConnectorStatus::Connected && $driver instanceof SupportsOAuth) {
            try {
                $driver->afterConnect();
            } catch (Throwable $e) {
                // Webhook registration failing must not undo a good connection.
                $model->forceFill(['last_error' => 'Connected, but post-setup failed: '.$e->getMessage()])->save();
            }
        }

        return $status;
    }
}
