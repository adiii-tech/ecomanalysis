<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property ?string $row_hash
 * @property CarbonImmutable $date
 * @property string $page_path
 * @property ?string $page_title
 * @property int $views
 * @property int $users
 * @property int $events
 * @property float $avg_time_seconds
 * @property float $bounce_rate
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AnalyticsPage extends Model
{
    use BelongsToTenant;

    protected $table = 'analytics_pages';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'avg_time_seconds' => 'float',
            'bounce_rate' => 'float',
        ];
    }
}
