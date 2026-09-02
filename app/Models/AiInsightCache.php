<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $cache_key
 * @property string $widget_key
 * @property string $content
 * @property array $payload_digest
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AiInsightCache extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_insight_cache';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'payload_digest' => 'array',
        ];
    }
}
