<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reports\Jobs\SendDigest;
use App\Models\NotificationSetting;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Queues the standing digests that are due this hour, in each tenant's own
 * timezone. Runs hourly.
 */
class SendDigests extends Command
{
    protected $signature = 'digests:send {--dry-run : List what would be sent without queueing anything}';

    protected $description = 'Queue the daily brief, weekly review and monthly P&L digests that are due now.';

    public function handle(TenantContext $context): int
    {
        $queued = 0;

        $settings = $context->withoutScope(fn () => NotificationSetting::query()->with('tenant')->get());

        foreach ($settings as $setting) {
            $tenant = $setting->tenant;

            if ($tenant === null) {
                continue;
            }

            foreach ($this->dueKinds($setting, $tenant) as $kind) {
                $queued++;
                $this->line(sprintf('%s · %s', $tenant->name, str_replace('_', ' ', $kind)));

                if (! $this->option('dry-run')) {
                    SendDigest::dispatch($tenant->id, $kind);
                }
            }
        }

        $this->info(sprintf('%d digest(s) due.', $queued));

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function dueKinds(NotificationSetting $setting, Tenant $tenant): array
    {
        $now = CarbonImmutable::now($tenant->timezone);
        $hour = $setting->daily_brief_hour;
        $due = [];

        if ($now->hour !== $hour) {
            return $due;
        }

        if (filled($setting->daily_brief_recipients) && $this->notSentThisWindow($setting->daily_brief_sent_at, $now->startOfDay())) {
            $due[] = SendDigest::DAILY;
        }

        if (filled($setting->weekly_review_recipients)
            && $now->dayOfWeek === $setting->weekly_review_day
            && $this->notSentThisWindow($setting->weekly_review_sent_at, $now->startOfDay())) {
            $due[] = SendDigest::WEEKLY;
        }

        if (filled($setting->monthly_pnl_recipients)
            && $now->day === 1
            && $this->notSentThisWindow($setting->monthly_pnl_sent_at, $now->startOfDay())) {
            $due[] = SendDigest::MONTHLY;
        }

        return $due;
    }

    private function notSentThisWindow(?CarbonImmutable $sentAt, CarbonImmutable $windowStart): bool
    {
        return $sentAt === null || $sentAt->lessThan($windowStart);
    }
}
