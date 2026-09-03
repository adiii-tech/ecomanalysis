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
 * @property int $purchase_order_id
 * @property int $sku_id
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property int $unit_cost
 * @property float $tax_rate
 * @property int $line_total
 * @property int $landed_unit_cost
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class PurchaseOrderItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Sku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function quantityOutstanding(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_received);
    }
}
