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
 * @property ?int $order_id
 * @property ?int $channel_id
 * @property string $fee_type
 * @property int $amount
 * @property ?string $settlement_id
 * @property ?CarbonImmutable $settled_at
 * @property ?CarbonImmutable $fee_date
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class MarketplaceFee extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settled_at' => 'datetime',
            'fee_date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
