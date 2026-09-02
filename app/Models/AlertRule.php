<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property ?int $created_by
 * @property string $name
 * @property string $metric
 * @property ?string $dimension
 * @property string $operator
 * @property float $threshold
 * @property int $window_days
 * @property string $scope
 * @property array $scope_values
 * @property array $channels
 * @property string $frequency
 * @property bool $is_active
 * @property bool $is_muted
 * @property ?CarbonImmutable $muted_until
 * @property ?CarbonImmutable $last_evaluated_at
 * @property ?CarbonImmutable $last_triggered_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AlertRule extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope_values' => 'array',
            'channels' => 'array',
            'is_active' => 'boolean',
            'is_muted' => 'boolean',
            'muted_until' => 'datetime',
            'last_evaluated_at' => 'datetime',
            'last_triggered_at' => 'datetime',
            'threshold' => 'float',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AlertEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }
}
