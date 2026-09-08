<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A batch of stock that arrived together, at one cost, with one expiry.
 *
 * Batches are what make "which units did we actually sell" answerable — both
 * for expiry (oldest goes first) and for valuation (what those units cost).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $sku_id
 * @property ?int $location_id
 * @property string $batch_code
 * @property ?CarbonImmutable $manufactured_on
 * @property ?CarbonImmutable $expires_on
 * @property int $quantity
 * @property int $quantity_received
 * @property int $unit_cost
 * @property ?string $source_type
 * @property ?int $source_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class StockBatch extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'quantity' => 0,
        'quantity_received' => 0,
        'unit_cost' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'manufactured_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /** @return BelongsTo<Sku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function daysToExpiry(): ?int
    {
        return $this->expires_on === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->expires_on->startOfDay(), absolute: false);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }
}
