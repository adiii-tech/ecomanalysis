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
 * @property ?int $sku_id
 * @property ?int $product_id
 * @property string $source
 * @property string $external_id
 * @property ?string $product_sku
 * @property int $rating
 * @property ?string $title
 * @property ?string $body
 * @property ?string $reviewer
 * @property bool $verified
 * @property bool $has_photos
 * @property ?string $sentiment
 * @property ?float $sentiment_score
 * @property array $themes
 * @property ?CarbonImmutable $analysed_at
 * @property CarbonImmutable $reviewed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Review extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'analysed_at' => 'datetime',
            'verified' => 'boolean',
            'has_photos' => 'boolean',
            'themes' => 'array',
            'sentiment_score' => 'float',
        ];
    }

    /** @return BelongsTo<Sku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
