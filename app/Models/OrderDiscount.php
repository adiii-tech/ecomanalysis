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
 * @property int $order_id
 * @property string $code
 * @property string $type
 * @property int $value
 * @property int $amount
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class OrderDiscount extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
