<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property float $target_roas
 * @property float $target_margin_pct
 * @property float $target_repeat_rate
 * @property float $target_cac
 * @property int $dispatch_sla_days
 * @property int $delivery_sla_days
 * @property float $rto_threshold_pct
 * @property float $return_threshold_pct
 * @property int $days_of_cover_threshold
 * @property int $monthly_revenue_target
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Benchmark extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * Mirrors the column defaults. `firstOrCreate()` hands back only the
     * attributes it inserted, so without these a tenant's first read would see
     * null thresholds and every widget would report a breach.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'target_roas' => 3.0,
        'target_margin_pct' => 25.0,
        'target_repeat_rate' => 25.0,
        'target_cac' => 0,
        'dispatch_sla_days' => 2,
        'delivery_sla_days' => 7,
        'rto_threshold_pct' => 15.0,
        'return_threshold_pct' => 10.0,
        'days_of_cover_threshold' => 14,
        'monthly_revenue_target' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_roas' => 'float',
            'target_margin_pct' => 'float',
            'target_repeat_rate' => 'float',
            'target_cac' => 'float',
            'rto_threshold_pct' => 'float',
            'return_threshold_pct' => 'float',
        ];
    }
}
