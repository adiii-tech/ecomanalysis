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
 * @property ?string $row_hash
 * @property CarbonImmutable $date
 * @property ?int $sku_id
 * @property ?string $item_id
 * @property ?string $item_name
 * @property int $views
 * @property int $add_to_carts
 * @property int $checkouts
 * @property int $purchases
 * @property int $revenue
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AnalyticsProduct extends Model
{
    use BelongsToTenant;

    protected $table = 'analytics_products';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Sku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }
}
