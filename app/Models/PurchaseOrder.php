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
 * @property ?int $supplier_id
 * @property ?int $location_id
 * @property ?int $created_by
 * @property string $po_number
 * @property string $status
 * @property ?CarbonImmutable $expected_at
 * @property int $freight_cost
 * @property int $other_cost
 * @property int $subtotal
 * @property int $tax_amount
 * @property int $total
 * @property ?string $notes
 * @property ?CarbonImmutable $sent_at
 * @property ?CarbonImmutable $received_at
 * @property ?CarbonImmutable $cancelled_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class PurchaseOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_at' => 'date',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Only a draft can still be edited; once sent, the supplier has it. */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, ['sent', 'partial'], true);
    }
}
