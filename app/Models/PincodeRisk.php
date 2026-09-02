<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $pincode
 * @property ?string $city
 * @property ?string $state
 * @property int $shipments_count
 * @property int $rto_count
 * @property float $rto_rate
 * @property int $risk_score
 * @property string $risk_band
 * @property bool $cod_serviceable
 * @property ?CarbonImmutable $computed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class PincodeRisk extends Model
{
    use BelongsToTenant;

    protected $table = 'pincode_risk';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cod_serviceable' => 'boolean',
            'computed_at' => 'datetime',
            'rto_rate' => 'float',
        ];
    }
}
