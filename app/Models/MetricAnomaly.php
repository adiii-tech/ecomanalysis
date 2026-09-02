<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property CarbonImmutable $date
 * @property string $metric
 * @property ?string $dimension
 * @property float $value
 * @property float $expected
 * @property float $z_score
 * @property string $direction
 * @property string $severity
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class MetricAnomaly extends Model
{
    use BelongsToTenant;

    protected $table = 'metric_anomalies';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'value' => 'float',
            'expected' => 'float',
            'z_score' => 'float',
        ];
    }
}
