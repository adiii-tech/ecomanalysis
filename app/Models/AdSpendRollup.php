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
 * @property string $platform
 * @property int $spend
 * @property int $impressions
 * @property int $clicks
 * @property int $conversions
 * @property int $conversion_value
 * @property ?CarbonImmutable $computed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AdSpendRollup extends Model
{
    use BelongsToTenant;

    protected $table = 'ad_spend_rollup';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'computed_at' => 'datetime',
        ];
    }
}
