<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $cohort_month
 * @property int $month_index
 * @property int $cohort_size
 * @property int $active_customers
 * @property float $retention_pct
 * @property int $revenue
 * @property int $margin
 * @property int $cumulative_revenue
 * @property int $avg_ltv
 * @property ?CarbonImmutable $computed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class CohortSnapshot extends Model
{
    use BelongsToTenant;

    protected $table = 'cohort_snapshots';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime',
            'retention_pct' => 'float',
        ];
    }
}
