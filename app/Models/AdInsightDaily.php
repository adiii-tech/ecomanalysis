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
 * @property ?string $row_hash
 * @property CarbonImmutable $date
 * @property string $platform
 * @property ?int $campaign_id
 * @property ?int $ad_set_id
 * @property ?int $ad_id
 * @property string $breakdown_key
 * @property ?string $placement
 * @property ?string $age
 * @property ?string $gender
 * @property ?string $country
 * @property ?string $state
 * @property int $spend
 * @property int $impressions
 * @property int $clicks
 * @property int $reach
 * @property int $conversions
 * @property int $conversion_value
 * @property int $add_to_carts
 * @property int $checkouts
 * @property int $video_views_3s
 * @property int $video_views_thruplay
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AdInsightDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'ad_insights_daily';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /** @return BelongsTo<AdSet, $this> */
    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class, 'ad_set_id');
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class, 'ad_id');
    }
}
