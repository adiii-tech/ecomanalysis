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
 * @property string $name
 * @property ?string $contact_name
 * @property ?string $email
 * @property ?string $phone
 * @property ?string $gstin
 * @property ?string $address
 * @property ?string $city
 * @property ?string $state
 * @property int $lead_time_days
 * @property int $payment_terms_days
 * @property ?string $notes
 * @property bool $is_active
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Supplier extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return HasMany<Sku, $this> */
    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }
}
