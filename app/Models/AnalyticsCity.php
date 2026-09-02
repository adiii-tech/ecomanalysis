<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property ?string $row_hash
 * @property CarbonImmutable $date
 * @property ?string $city
 * @property ?string $region
 * @property ?string $country
 * @property int $sessions
 * @property int $users
 * @property int $purchases
 * @property int $revenue
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AnalyticsCity extends Model
{
    use BelongsToTenant;

    protected $table = 'analytics_cities';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }
}
