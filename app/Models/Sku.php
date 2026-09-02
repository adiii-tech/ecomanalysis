<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property ?int $product_id
 * @property string $source
 * @property ?string $external_id
 * @property string $sku_code
 * @property string $name
 * @property ?string $variant_title
 * @property ?string $category
 * @property ?string $subcategory
 * @property ?string $brand
 * @property int $mrp
 * @property int $selling_price
 * @property int $cost_price
 * @property int $weight_grams
 * @property ?string $hsn
 * @property float $gst_rate
 * @property ?string $image_url
 * @property ?string $barcode
 * @property bool $is_combo
 * @property array $combo_children
 * @property bool $is_active
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Sku extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_combo' => 'boolean',
            'is_active' => 'boolean',
            'combo_children' => 'array',
            'gst_rate' => 'float',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<Inventory, $this> */
    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /** @return HasMany<SkuCostHistory, $this> */
    public function costHistory(): HasMany
    {
        return $this->hasMany(SkuCostHistory::class);
    }

    /**
     * Cost that applied on a given date, so historical margin stays correct
     * when a SKU's cost price changes.
     */
    public function costOn(string $date): int
    {
        $historical = $this->relationLoaded('costHistory')
            ? $this->costHistory->where('effective_from', '<=', $date)->sortByDesc('effective_from')->first()
            : $this->costHistory()->where('effective_from', '<=', $date)->orderByDesc('effective_from')->first();

        return $historical?->cost_price ?? $this->cost_price;
    }

    public function availableStock(): int
    {
        return (int) $this->inventory()->sum('available');
    }
}
