<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in the stock ledger. Append-only: a wrong movement is corrected by
 * recording another, never by editing this one, so the history always adds up
 * to the balance on hand.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $sku_id
 * @property ?int $location_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property int $balance_after
 * @property int $unit_cost
 * @property ?string $reason
 * @property ?string $note
 * @property ?string $reference_type
 * @property ?int $reference_id
 * @property ?int $user_id
 * @property CarbonImmutable $happened_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
/**
 * @property int $id
 * @property int $tenant_id
 * @property int $sku_id
 * @property ?int $location_id
 * @property ?int $stock_batch_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property int $balance_after
 * @property int $unit_cost
 * @property ?string $reason
 * @property ?string $note
 * @property ?string $reference_type
 * @property ?int $reference_id
 * @property ?int $user_id
 * @property CarbonImmutable $happened_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class StockMovement extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'happened_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
