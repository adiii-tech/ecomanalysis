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
 * @property string $platform
 * @property string $media_id
 * @property string $type
 * @property ?string $caption
 * @property ?string $permalink
 * @property ?string $thumbnail_url
 * @property CarbonImmutable $published_at
 * @property int $reach
 * @property int $impressions
 * @property int $views
 * @property int $likes
 * @property int $comments
 * @property int $shares
 * @property int $saves
 * @property int $replies
 * @property float $avg_watch_time
 * @property float $engagement_rate
 * @property array $hashtags
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class SocialPost extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'hashtags' => 'array',
            'engagement_rate' => 'float',
            'avg_watch_time' => 'float',
        ];
    }

    /** @return BelongsTo<SocialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
