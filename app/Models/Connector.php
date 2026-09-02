<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthType;
use App\Enums\ConnectorStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $connector_id
 * @property ConnectorStatus $status
 * @property AuthType $auth_type
 * @property array $credentials
 * @property ?string $account_label
 * @property bool $has_secret
 * @property ?CarbonImmutable $last_connected_at
 * @property ?CarbonImmutable $last_synced_at
 * @property ?string $last_error
 * @property array $sync_cursor
 * @property array $settings
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Connector extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['credentials'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'sync_cursor' => 'array',
            'settings' => 'array',
            'status' => ConnectorStatus::class,
            'auth_type' => AuthType::class,
            'has_secret' => 'boolean',
            'last_connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class, 'connector_id', 'connector_id')
            ->where('tenant_id', $this->tenant_id);
    }

    public function isConnected(): bool
    {
        return $this->status === ConnectorStatus::Connected;
    }

    public function cursorFor(string $entity): mixed
    {
        return data_get($this->sync_cursor, $entity);
    }

    public function setCursorFor(string $entity, mixed $value): void
    {
        $cursor = $this->sync_cursor ?? [];
        $cursor[$entity] = $value;
        $this->sync_cursor = $cursor;
    }
}
