<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $source
 * @property ?string $external_id
 * @property string $title
 * @property ?string $handle
 * @property ?string $category
 * @property ?string $subcategory
 * @property ?string $brand
 * @property ?string $product_type
 * @property string $status
 * @property ?string $image_url
 * @property array $tags
 * @property ?CarbonImmutable $published_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Product extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<Sku, $this> */
    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }
}
