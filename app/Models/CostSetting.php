<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $cogs_method
 * @property int $packaging_cost
 * @property int $per_order_fixed_cost
 * @property int $cod_charge
 * @property int $return_handling_cost
 * @property int $rto_handling_cost
 * @property int $default_shipping_cost
 * @property float $gateway_fee_pct
 * @property string $gst_mode
 * @property int $monthly_fixed_opex
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class CostSetting extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * Mirrors the column defaults so a tenant's first `firstOrCreate()` read
     * costs money at the documented rates rather than at zero.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cogs_method' => 'sku_cost',
        'packaging_cost' => 0,
        'per_order_fixed_cost' => 0,
        'cod_charge' => 0,
        'return_handling_cost' => 0,
        'rto_handling_cost' => 0,
        'default_shipping_cost' => 0,
        'gateway_fee_pct' => 2.0,
        'gst_mode' => 'inclusive',
        'monthly_fixed_opex' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gateway_fee_pct' => 'float',
        ];
    }
}
