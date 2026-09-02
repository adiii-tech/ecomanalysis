<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMode;
use App\Enums\ShipmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $order_id
 * @property ?int $channel_id
 * @property string $source
 * @property ?string $external_id
 * @property ?string $courier
 * @property ?string $awb
 * @property ShipmentStatus $status
 * @property PaymentMode $payment_mode
 * @property ?string $destination_state
 * @property ?string $destination_city
 * @property ?string $destination_pincode
 * @property ?CarbonImmutable $manifested_at
 * @property ?CarbonImmutable $dispatched_at
 * @property ?CarbonImmutable $delivered_at
 * @property ?CarbonImmutable $promised_at
 * @property int $attempts
 * @property ?string $ndr_reason
 * @property ?string $ndr_status
 * @property bool $is_rto
 * @property ?CarbonImmutable $rto_at
 * @property ?CarbonImmutable $cod_collected_at
 * @property ?CarbonImmutable $cod_remitted_at
 * @property int $cod_amount
 * @property int $shipping_cost
 * @property int $rto_cost
 * @property ?int $transit_days
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Shipment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'payment_mode' => PaymentMode::class,
            'manifested_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'promised_at' => 'datetime',
            'rto_at' => 'datetime',
            'cod_collected_at' => 'datetime',
            'cod_remitted_at' => 'datetime',
            'is_rto' => 'boolean',
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

    public function isLate(int $slaDays): bool
    {
        if ($this->delivered_at === null || $this->dispatched_at === null) {
            return false;
        }

        return $this->dispatched_at->diffInDays($this->delivered_at) > $slaDays;
    }
}
