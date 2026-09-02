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
 * @property string $source
 * @property string $external_id
 * @property ?int $customer_id
 * @property CarbonImmutable $abandoned_at
 * @property int $cart_value
 * @property int $items_count
 * @property bool $recovered
 * @property ?CarbonImmutable $recovered_at
 * @property ?string $recovery_url
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AbandonedCheckout extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'abandoned_at' => 'datetime',
            'recovered_at' => 'datetime',
            'recovered' => 'boolean',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
