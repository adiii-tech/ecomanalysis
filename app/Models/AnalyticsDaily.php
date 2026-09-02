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
 * @property string $channel_group
 * @property ?string $source
 * @property ?string $medium
 * @property ?string $campaign
 * @property int $sessions
 * @property int $users
 * @property int $new_users
 * @property int $engaged_sessions
 * @property float $bounce_rate
 * @property int $item_views
 * @property int $add_to_carts
 * @property int $checkouts
 * @property int $purchases
 * @property int $revenue
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AnalyticsDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'analytics_daily';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'bounce_rate' => 'float',
        ];
    }
}
