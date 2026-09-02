<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $alert_rule_id
 * @property string $title
 * @property string $body
 * @property string $severity
 * @property float $observed_value
 * @property float $threshold
 * @property ?string $dimension_value
 * @property array $delivery_status
 * @property ?CarbonImmutable $read_at
 * @property ?CarbonImmutable $snoozed_until
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AlertEvent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'delivery_status' => 'array',
            'read_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'observed_value' => 'float',
            'threshold' => 'float',
        ];
    }

    /** @return BelongsTo<AlertRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }
}
