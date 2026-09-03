<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMode;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single source of truth for every dashboard number. One row per
 * (tenant, date, channel, payment_mode). Dashboards never touch raw orders.
 *
 * @property int $id
 * @property int $tenant_id
 * @property CarbonImmutable $date
 * @property ?int $channel_id
 * @property PaymentMode $payment_mode
 * @property int $orders_count
 * @property int $units_count
 * @property int $items_count
 * @property int $customers_count
 * @property int $new_customers
 * @property int $repeat_customers
 * @property int $gross_sales
 * @property int $discounts
 * @property int $cancelled_amount
 * @property int $cancelled_orders
 * @property int $invoiced_sales
 * @property int $invoiced_orders
 * @property int $returned_amount
 * @property int $returned_orders
 * @property int $rto_amount
 * @property int $rto_orders
 * @property int $net_sales
 * @property int $tax_amount
 * @property int $shipping_collected
 * @property int $cogs
 * @property int $marketplace_fees
 * @property int $logistics_cost
 * @property int $packaging_cost
 * @property int $gateway_fees
 * @property int $return_cost
 * @property int $contribution_margin
 * @property int $shipments_count
 * @property int $delivered_count
 * @property int $in_transit_count
 * @property int $loss_orders
 * @property int $loss_amount
 * @property ?CarbonImmutable $computed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class DailyMetricsRollup extends Model
{
    use BelongsToTenant;

    protected $table = 'daily_metrics_rollup';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'payment_mode' => PaymentMode::class,
            'computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Every additive column, used by the aggregation queries so a new metric
     * only has to be added in one place.
     *
     * @return list<string>
     */
    public static function sumColumns(): array
    {
        return [
            'orders_count', 'units_count', 'items_count', 'customers_count', 'new_customers', 'repeat_customers',
            'gross_sales', 'discounts', 'cancelled_amount', 'cancelled_orders', 'invoiced_sales', 'invoiced_orders',
            'returned_amount', 'returned_orders', 'rto_amount', 'rto_orders', 'net_sales', 'tax_amount',
            'shipping_collected', 'cogs', 'marketplace_fees', 'logistics_cost', 'packaging_cost', 'gateway_fees',
            'return_cost', 'contribution_margin', 'shipments_count', 'delivered_count', 'in_transit_count',
            'loss_orders', 'loss_amount',
        ];
    }
}
