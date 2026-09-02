<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $currency
 * @property string $timezone
 * @property ?string $gst_state
 * @property ?string $gstin
 * @property int $fiscal_year_start
 * @property Plan $plan
 * @property int $ai_credit_limit
 * @property int $ai_credits_used
 * @property bool $is_demo
 * @property array $onboarding_state
 * @property ?string $logo_url
 * @property ?CarbonImmutable $trial_ends_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?CarbonImmutable $deleted_at
 */
class Tenant extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'onboarding_state' => 'array',
            'is_demo' => 'boolean',
            'plan' => Plan::class,
            'trial_ends_at' => 'datetime',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Channel, $this> */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    /** @return HasMany<Connector, $this> */
    public function connectors(): HasMany
    {
        return $this->hasMany(Connector::class);
    }

    /** @return HasOne<CostSetting, $this> */
    public function costSetting(): HasOne
    {
        return $this->hasOne(CostSetting::class);
    }

    /** @return HasOne<Benchmark, $this> */
    public function benchmark(): HasOne
    {
        return $this->hasOne(Benchmark::class);
    }

    public function aiCreditsRemaining(): int
    {
        return max(0, $this->ai_credit_limit - $this->ai_credits_used);
    }

    public function hasAiCredits(): bool
    {
        return $this->aiCreditsRemaining() > 0;
    }
}
