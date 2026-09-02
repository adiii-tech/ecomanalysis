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
 * @property ?int $ad_set_id
 * @property ?int $campaign_id
 * @property string $platform
 * @property string $external_id
 * @property string $name
 * @property string $status
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Ad extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<AdSet, $this> */
    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class, 'ad_set_id');
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /** @return HasMany<AdCreative, $this> */
    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }
}
