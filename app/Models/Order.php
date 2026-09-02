<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property ?int $channel_id
 * @property ?int $customer_id
 * @property string $source
 * @property string $external_id
 * @property ?string $order_number
 * @property CarbonImmutable $placed_at
 * @property ?CarbonImmutable $invoiced_at
 * @property ?CarbonImmutable $cancelled_at
 * @property ?CarbonImmutable $delivered_at
 * @property OrderStatus $status
 * @property ?string $fulfillment_status
 * @property PaymentMode $payment_mode
 * @property ?string $shipping_state
 * @property ?string $shipping_city
 * @property ?string $shipping_pincode
 * @property string $currency
 * @property int $items_count
 * @property int $units_count
 * @property int $gross_amount
 * @property int $discount_amount
 * @property int $shipping_amount
 * @property int $tax_amount
 * @property int $invoiced_amount
 * @property int $cancelled_amount
 * @property int $returned_amount
 * @property int $rto_amount
 * @property int $net_amount
 * @property int $cogs_amount
 * @property int $fees_amount
 * @property int $logistics_amount
 * @property int $return_cost_amount
 * @property int $packaging_amount
 * @property int $gateway_fee_amount
 * @property int $contribution_margin
 * @property bool $is_first_order
 * @property bool $has_return
 * @property bool $is_rto
 * @property ?string $discount_codes
 * @property ?string $utm_source
 * @property ?string $utm_medium
 * @property ?string $utm_campaign
 * @property array $raw
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Order extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'delivered_at' => 'datetime',
            'status' => OrderStatus::class,
            'payment_mode' => PaymentMode::class,
            'is_first_order' => 'boolean',
            'has_return' => 'boolean',
            'is_rto' => 'boolean',
            'raw' => 'array',
        ];
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasMany<OrderReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<MarketplaceFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(MarketplaceFee::class);
    }

    /** @return HasMany<OrderDiscount, $this> */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }

    /** @param Builder<Order> $query */
    public function scopePlacedBetween(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('placed_at', [$from, $to]);
    }

    /** @param Builder<Order> $query */
    public function scopeInvoiced(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::invoicedValues());
    }

    /** @param Builder<Order> $query */
    public function scopeLossMaking(Builder $query): void
    {
        $query->where('contribution_margin', '<', 0);
    }

    public function marginPct(): float
    {
        return $this->net_amount === 0 ? 0.0 : round($this->contribution_margin / $this->net_amount * 100, 2);
    }
}
