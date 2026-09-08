<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a bundle: how many of a child SKU go into the parent.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $parent_sku_id
 * @property int $child_sku_id
 * @property int $quantity
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class SkuComponent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Sku, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'parent_sku_id');
    }

    /** @return BelongsTo<Sku, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'child_sku_id');
    }
}
