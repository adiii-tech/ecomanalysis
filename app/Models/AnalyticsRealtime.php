<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property CarbonImmutable $captured_at
 * @property int $active_users
 * @property array $by_country
 * @property array $by_page
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AnalyticsRealtime extends Model
{
    use BelongsToTenant;

    protected $table = 'analytics_realtime';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'by_country' => 'array',
            'by_page' => 'array',
        ];
    }
}
