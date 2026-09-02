<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $kind
 * @property ?string $category
 * @property string $severity
 * @property string $title
 * @property string $body
 * @property int $impact_amount
 * @property int $rank_score
 * @property array $evidence
 * @property ?string $link
 * @property ?CarbonImmutable $period_start
 * @property ?CarbonImmutable $period_end
 * @property ?CarbonImmutable $dismissed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AiInsight extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_insights';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }
}
