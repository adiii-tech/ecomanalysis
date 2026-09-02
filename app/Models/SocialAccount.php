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
 * @property string $platform
 * @property string $external_id
 * @property ?string $username
 * @property ?string $name
 * @property ?string $profile_picture_url
 * @property int $followers_count
 * @property int $follows_count
 * @property int $media_count
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class SocialAccount extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return HasMany<SocialPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }
}
