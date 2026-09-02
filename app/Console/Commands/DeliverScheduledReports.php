<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reports\Jobs\DeliverScheduledReport;
use App\Models\ReportSchedule;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Queues the report deliveries that are due this hour. Runs hourly; each
 * schedule fires at most once per window because `last_sent_at` is checked
 * against the start of the window, not against a fixed interval.
 */
class DeliverScheduledReports extends Command
{
    protected $signature = 'reports:deliver {--dry-run : List what would be sent without queueing anything}';

    protected $description = 'Queue any scheduled report deliveries that are due now.';

    public function handle(TenantContext $context): int
    {
        $due = $context->withoutScope(fn () => ReportSchedule::query()
            ->with('tenant')
            ->where('is_active', true)
            ->get()
            ->filter(fn (ReportSchedule $schedule): bool => $this->isDue($schedule)));

        foreach ($due as $schedule) {
            $this->line(sprintf(
                '%s · %s · %s → %s',
                $schedule->tenant->name,
                $schedule->report_key,
                $schedule->cadence,
                implode(', ', $schedule->recipients),
            ));

            if (! $this->option('dry-run')) {
                DeliverScheduledReport::dispatch($schedule->id);
            }
        }

        $this->info(sprintf('%d schedule(s) due.', $due->count()));

        return self::SUCCESS;
    }

    private function isDue(ReportSchedule $schedule): bool
    {
        $now = CarbonImmutable::now($schedule->tenant->timezone);

        if ($now->hour !== $schedule->hour) {
            return false;
        }

        $matchesCadence = match ($schedule->cadence) {
            'daily' => true,
            'weekly' => $now->dayOfWeek === ($schedule->day_of_week ?? 1),
            'monthly' => $now->day === ($schedule->day_of_month ?? 1),
            default => false,
        };

        if (! $matchesCadence) {
            return false;
        }

        // Already sent inside this hour's window.
        return $schedule->last_sent_at === null
            || $schedule->last_sent_at->setTimezone($schedule->tenant->timezone)->lessThan($now->startOfHour());
    }
}
