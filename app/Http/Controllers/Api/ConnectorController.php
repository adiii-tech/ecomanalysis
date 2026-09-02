<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Connectors\Actions\CompleteOAuth;
use App\Domain\Connectors\Actions\ConnectConnector;
use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\Contracts\SupportsOAuth;
use App\Domain\Connectors\Jobs\SyncConnectorEntity;
use App\Domain\Connectors\Queries\SyncHealthQuery;
use App\Enums\ConnectorStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Connector;
use App\Models\Connector as ConnectorModel;
use App\Models\SyncRun;
use App\Support\Facades\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class ConnectorController extends Controller
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly SyncHealthQuery $health,
    ) {}

    public function index(): JsonResponse
    {
        $connected = Connector::query()->get()->keyBy('connector_id');

        $rows = collect($this->registry->catalogue())->map(function (array $driver) use ($connected): array {
            $model = $connected->get($driver['id']);

            $instance = $this->registry->make($driver['id']);
            $isOAuth = $instance instanceof SupportsOAuth;

            return [
                ...$driver,
                'status' => $model?->status->value ?? ConnectorStatus::Disconnected->value,
                'status_label' => ($model?->status ?? ConnectorStatus::Disconnected)->label(),
                'account_label' => $model?->account_label,
                'last_connected_at' => $model?->last_connected_at?->toIso8601String(),
                'last_synced_at' => $model?->last_synced_at?->toIso8601String(),
                'last_synced_human' => $model?->last_synced_at?->diffForHumans(),
                'last_error' => $model?->last_error,
                'is_oauth' => $isOAuth,
                'oauth_configured' => $isOAuth && $this->oauthIsConfigured($driver['id']),
                'pre_auth_fields' => $isOAuth ? $instance->preAuthFields() : [],
                'pending_selections' => $isOAuth && $model !== null
                    ? collect($instance->pendingSelections())
                        ->reject(fn (string $label, string $key): bool => filled(data_get($model->credentials, $key)))
                        ->all()
                    : [],
                'selected' => $isOAuth && $model !== null
                    ? collect($instance->pendingSelections())
                        ->mapWithKeys(fn (string $label, string $key): array => [$key => data_get($model->credentials, $key)])
                        ->filter()
                        ->all()
                    : [],
            ];
        });

        return ApiResponse::ok([
            'live' => $rows->reject(fn (array $row): bool => $row['is_stub'])->values()->all(),
            'phase_two' => $rows->filter(fn (array $row): bool => $row['is_stub'])->values()->all(),
            'connected_count' => $connected->where('status', ConnectorStatus::Connected)->count(),
            'health' => $this->health->summary(),
        ]);
    }

    public function syncRuns(Request $request): JsonResponse
    {
        $runs = SyncRun::query()
            ->when($request->filled('connector'), fn ($q) => $q->where('connector_id', $request->string('connector')))
            ->orderByDesc('started_at')
            ->limit(100)
            ->get()
            ->map(static fn (SyncRun $run): array => [
                'id' => $run->id,
                'connector_id' => $run->connector_id,
                'entity' => $run->entity,
                'status' => $run->status->value,
                'trigger' => $run->trigger,
                'started_at' => $run->started_at?->toIso8601String(),
                'records_fetched' => $run->records_fetched,
                'records_upserted' => $run->records_upserted,
                'duration_ms' => $run->duration_ms,
                'error' => $run->error,
            ]);

        return ApiResponse::ok(['rows' => $runs->all()]);
    }

    public function connect(Request $request, string $connector, ConnectConnector $action): JsonResponse
    {
        if (! $this->registry->has($connector)) {
            return ApiResponse::error("Unknown connector [{$connector}].", 404);
        }

        $driver = $this->registry->make($connector);

        if ($driver->isStub()) {
            return ApiResponse::error(
                sprintf('%s ships in phase 2 — its driver is not implemented yet, so connecting it would not pull any data.', $driver->label()),
                422,
            );
        }

        $limit = (int) Feature::value('connector-limit');
        $connected = ConnectorModel::query()->where('status', '!=', ConnectorStatus::Disconnected)->count();
        $alreadyConnected = ConnectorModel::query()->where('connector_id', $connector)->exists();

        if (! $alreadyConnected && $connected >= $limit) {
            return ApiResponse::error(
                $limit === 0
                    ? 'Connectors are not part of the demo plan. Upgrade to connect your own data.'
                    : sprintf('Your %s plan covers %d connectors and %d are in use. Upgrade or disconnect one first.',
                        Tenant::current()->plan->label(), $limit, $connected),
                422,
                ['upgrade_required' => true, 'limit' => $limit],
            );
        }

        $result = $action->handle(Tenant::current(), $connector, $request->all());

        return $result->ok
            ? ApiResponse::ok(['account_label' => $result->accountLabel], message: 'Connected.')
            : ApiResponse::error($result->error ?? 'Could not connect.', 422);
    }

    public function disconnect(string $connector): JsonResponse
    {
        Connector::query()->where('connector_id', $connector)->update([
            'status' => ConnectorStatus::Disconnected,
            'credentials' => null,
            'has_secret' => false,
        ]);

        activity('connector')->withProperties(['connector' => $connector])->log('connector.disconnected');

        return ApiResponse::ok(null, message: 'Disconnected.');
    }

    public function test(string $connector): JsonResponse
    {
        if (! $this->registry->has($connector)) {
            return ApiResponse::error("Unknown connector [{$connector}].", 404);
        }

        return ApiResponse::ok($this->registry->forTenant($connector)->testConnection()->toArray());
    }

    public function sync(Request $request, string $connector): JsonResponse
    {
        if (! $this->registry->has($connector)) {
            return ApiResponse::error("Unknown connector [{$connector}].", 404);
        }

        $model = Connector::query()->where('connector_id', $connector)->first();

        if ($model === null || ! $model->isConnected()) {
            return ApiResponse::error('That connector is not connected yet.', 422);
        }

        $entities = $this->registry->make($connector)->syncableEntities();

        foreach ($entities as $index => $entity) {
            SyncConnectorEntity::dispatch(
                Tenant::id(),
                $connector,
                $entity,
                'manual',
                rebuildRollups: $index === array_key_last($entities),
            );
        }

        return ApiResponse::ok(['queued_entities' => $entities], message: 'Sync queued.');
    }

    /**
     * Options for one thing the user still has to choose (which ad account,
     * which GA4 property). Read live from the provider with the stored token.
     */
    public function resources(string $connector, string $key): JsonResponse
    {
        $driver = $this->registry->has($connector) ? $this->registry->forTenant($connector) : null;

        if (! $driver instanceof SupportsOAuth) {
            return ApiResponse::error('That connector has nothing to select.', 404);
        }

        if (! array_key_exists($key, $driver->pendingSelections())) {
            return ApiResponse::error("Unknown selection [{$key}].", 404);
        }

        try {
            $options = $driver->availableResources($key);
        } catch (\Throwable $e) {
            return ApiResponse::error('Could not read that list from the provider: '.$e->getMessage(), 422);
        }

        return ApiResponse::ok([
            'key' => $key,
            'label' => $driver->pendingSelections()[$key],
            'options' => $options,
            'caveat' => $options === []
                ? 'The provider returned no options for this account. Check that the authorising user actually has access.'
                : null,
        ]);
    }

    /**
     * Save the chosen accounts and, once nothing is outstanding, flip the
     * connector to connected.
     */
    public function select(Request $request, string $connector, CompleteOAuth $complete): JsonResponse
    {
        $model = Connector::query()->where('connector_id', $connector)->first();
        $driver = $this->registry->has($connector) ? $this->registry->make($connector) : null;

        if ($model === null || ! $driver instanceof SupportsOAuth) {
            return ApiResponse::error('That connector is not authorised yet.', 422);
        }

        $allowed = array_keys($driver->pendingSelections());
        $selection = array_filter(
            $request->only($allowed),
            static fn (mixed $value): bool => filled($value),
        );

        if ($selection === []) {
            return ApiResponse::error('Nothing was selected.', 422);
        }

        $model->forceFill(['credentials' => [...$model->credentials, ...$selection]])->save();

        $status = $complete->settleStatus($model->fresh(), $driver);

        activity('connector')
            ->performedOn($model)
            ->withProperties(['connector' => $connector, 'selected' => array_keys($selection)])
            ->log('connector.configured');

        return ApiResponse::ok(
            ['status' => $status->value, 'status_label' => $status->label()],
            message: $status === ConnectorStatus::Connected ? 'Connector is ready.' : 'Saved — still needs the rest.',
        );
    }

    private function oauthIsConfigured(string $connector): bool
    {
        return filled(match ($connector) {
            'shopify' => config('services.shopify.client_id'),
            'meta' => config('services.meta.client_id'),
            'google_ads', 'ga4' => config('services.google.client_id'),
            default => null,
        });
    }
}
