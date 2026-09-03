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
 * @property int $stock_count_id
 * @property int $sku_id
 * @property int $expected_quantity
 * @property ?int $counted_quantity
 * @property ?string $reason
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class StockCountItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<StockCount, $this> */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    /** @return BelongsTo<Sku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    /** Positive means more on the shelf than the system believed. */
    public function variance(): ?int
    {
        return $this->counted_quantity === null
            ? null
            : $this->counted_quantity - $this->expected_quantity;
    }
}
