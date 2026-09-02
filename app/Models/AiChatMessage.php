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
 * @property int $ai_chat_session_id
 * @property string $role
 * @property ?string $content
 * @property array $tool_calls
 * @property array $attachments
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $latency_ms
 * @property ?string $model
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AiChatMessage extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_chat_messages';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'attachments' => 'array',
        ];
    }

    /** @return BelongsTo<AiChatSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'ai_chat_session_id');
    }
}
