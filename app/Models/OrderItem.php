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
 * @property ?int $sku_id
 * @property ?string $external_id
 * @property ?string $sku_code
 * @property ?string $title
 * @property int $qty
 * @property int $unit_price
 * @property int $discount
 * @property int $tax
 * @property int $cogs_unit
 * @property int $line_gross
 * @property int $line_net
 * @property int $line_fees
 * @property int $line_margin
 * @property string $status
 * @property int $returned_qty
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class OrderItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Sku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
