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
 * @property string $state
 * @property ?int $channel_id
 * @property int $orders_count
 * @property int $gross_sales
 * @property int $net_sales
 * @property int $margin
 * @property int $rto_count
 * @property int $returned_count
 * @property int $delivered_count
 * @property int $cod_orders
 * @property ?CarbonImmutable $computed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class StateDailyRollup extends Model
{
    use BelongsToTenant;

    protected $table = 'state_daily_rollup';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
