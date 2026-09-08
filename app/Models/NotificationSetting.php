<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a tenant's alerts and digests are delivered. Secrets are encrypted at
 * rest; a channel with nothing configured is treated as unconfigured rather
 * than as a silent failure.
 *
 * @property int $id
 * @property int $tenant_id
 * @property array $email_recipients
 * @property ?string $slack_webhook_url
 * @property ?string $whatsapp_phone_number_id
 * @property ?string $whatsapp_token
 * @property ?string $whatsapp_template
 * @property array $whatsapp_recipients
 * @property array $daily_brief_recipients
 * @property int $daily_brief_hour
 * @property array $weekly_review_recipients
 * @property int $weekly_review_day
 * @property array $monthly_pnl_recipients
 * @property ?CarbonImmutable $daily_brief_sent_at
 * @property ?CarbonImmutable $weekly_review_sent_at
 * @property ?CarbonImmutable $monthly_pnl_sent_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
/**
 * @property int $id
 * @property int $tenant_id
 * @property array $email_recipients
 * @property ?string $slack_webhook_url
 * @property ?string $whatsapp_phone_number_id
 * @property ?string $whatsapp_token
 * @property ?string $whatsapp_template
 * @property array $whatsapp_recipients
 * @property array $daily_brief_recipients
 * @property int $daily_brief_hour
 * @property array $weekly_review_recipients
 * @property int $weekly_review_day
 * @property array $monthly_pnl_recipients
 * @property ?CarbonImmutable $daily_brief_sent_at
 * @property ?CarbonImmutable $weekly_review_sent_at
 * @property ?CarbonImmutable $monthly_pnl_sent_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class NotificationSetting extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'daily_brief_hour' => 8,
        'weekly_review_day' => 1,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_recipients' => 'array',
            'whatsapp_recipients' => 'array',
            'whatsapp_token' => 'encrypted',
            'daily_brief_recipients' => 'array',
            'weekly_review_recipients' => 'array',
            'monthly_pnl_recipients' => 'array',
            'daily_brief_sent_at' => 'datetime',
            'weekly_review_sent_at' => 'datetime',
            'monthly_pnl_sent_at' => 'datetime',
        ];
    }

    public function hasSlack(): bool
    {
        return filled($this->slack_webhook_url);
    }

    public function hasWhatsapp(): bool
    {
        return filled($this->whatsapp_phone_number_id)
            && filled($this->whatsapp_token)
            && filled($this->whatsapp_recipients);
    }
}
