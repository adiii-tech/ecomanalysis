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
 * @property int $social_account_id
 * @property CarbonImmutable $date
 * @property string $platform
 * @property int $followers
 * @property int $new_followers
 * @property int $reach
 * @property int $impressions
 * @property int $profile_views
 * @property int $website_clicks
 * @property int $views
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class SocialAccountDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'social_account_daily';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<SocialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
