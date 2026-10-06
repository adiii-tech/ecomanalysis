<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\AI\Services\ClaudeClient;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's own Anthropic API key and model choice, read by
 * {@see ClaudeClient} ahead of the app-wide
 * `ANTHROPIC_API_KEY` — so a brand on a paid plan can bill AI usage to their
 * own account instead of the shared one, without losing AI features if they
 * never set one.
 *
 * @property int $id
 * @property int $tenant_id
 * @property ?string $api_key
 * @property ?string $model
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AiSetting extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }
}
