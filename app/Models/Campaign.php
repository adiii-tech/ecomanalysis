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
 * @property ?int $ad_account_id
 * @property string $platform
 * @property string $external_id
 * @property string $name
 * @property ?string $objective
 * @property string $status
 * @property int $daily_budget
 * @property int $lifetime_budget
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $stopped_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Campaign extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AdAccount, $this> */
    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    /** @return HasMany<AdSet, $this> */
    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class);
    }

    /** @return HasMany<AdInsightDaily, $this> */
    public function insights(): HasMany
    {
        return $this->hasMany(AdInsightDaily::class);
    }
}
