<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnType;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $order_id
 * @property ?int $order_item_id
 * @property ?int $channel_id
 * @property ?int $sku_id
 * @property string $source
 * @property ?string $external_id
 * @property ReturnType $type
 * @property ?string $reason_code
 * @property ?string $reason_text
 * @property int $qty
 * @property CarbonImmutable $initiated_at
 * @property ?CarbonImmutable $received_at
 * @property int $refund_amount
 * @property int $loss_amount
 * @property bool $restock
 * @property ?string $shipping_state
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class OrderReturn extends Model
{
    use BelongsToTenant;

    protected $table = 'returns';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ReturnType::class,
            'initiated_at' => 'datetime',
            'received_at' => 'datetime',
            'restock' => 'boolean',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Sku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
