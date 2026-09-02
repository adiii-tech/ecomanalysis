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
 * @property ?int $channel_id
 * @property string $settlement_id
 * @property CarbonImmutable $cycle_start
 * @property CarbonImmutable $cycle_end
 * @property int $expected_amount
 * @property int $received_amount
 * @property int $variance_amount
 * @property string $status
 * @property ?CarbonImmutable $received_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Settlement extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cycle_start' => 'date:Y-m-d',
            'cycle_end' => 'date:Y-m-d',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
