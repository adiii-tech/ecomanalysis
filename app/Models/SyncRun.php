<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $connector_id
 * @property string $entity
 * @property SyncStatus $status
 * @property string $trigger
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $finished_at
 * @property int $records_fetched
 * @property int $records_upserted
 * @property int $duration_ms
 * @property ?string $error
 * @property array $cursor_before
 * @property array $cursor_after
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class SyncRun extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cursor_before' => 'array',
            'cursor_after' => 'array',
            'status' => SyncStatus::class,
        ];
    }
}
