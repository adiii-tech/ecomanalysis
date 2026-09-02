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
 * @property CarbonImmutable $date
 * @property int $sku_id
 * @property ?int $channel_id
 * @property int $units_sold
 * @property int $orders_count
 * @property int $gross_sales
 * @property int $net_sales
 * @property int $cogs
 * @property int $fees
 * @property int $margin
 * @property int $returned_units
 * @property int $returned_amount
 * @property ?CarbonImmutable $computed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class SkuDailyRollup extends Model
{
    use BelongsToTenant;

    protected $table = 'sku_daily_rollup';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
