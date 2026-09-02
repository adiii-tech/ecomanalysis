<?php

declare(strict_types=1);

namespace App\Domain\Reports\Jobs;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\ReportRegistry;
use App\Domain\Reports\Services\DatasetFileWriter;
use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Support\Period;
use App\Support\TenantContext;
use App\Support\WidgetFilters;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds one scheduled report and emails it. The file is generated fresh at
 * send time from the same dataset the on-screen table uses, so a recipient
 * never gets a stale or differently-computed number.
 */
class DeliverScheduledReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $scheduleId)
    {
        $this->onQueue('default');
    }

    public function handle(
        TenantContext $context,
        ReportRegistry $reports,
        DatasetRegistry $datasets,
        DatasetFileWriter $writer,
    ): void {
        $schedule = $context->withoutScope(fn (): ?ReportSchedule => ReportSchedule::query()
            ->with('tenant')
            ->find($this->scheduleId));

        if ($schedule === null || ! $schedule->is_active) {
            return;
        }

        $context->set($schedule->tenant);

        $report = $reports->find($schedule->report_key);

        if ($report === null) {
            Log::warning('Scheduled report points at a report that no longer exists.', ['schedule' => $schedule->id]);

            return;
        }

        $filters = $this->filtersFor($schedule);
        $payload = $report->build($filters)->toArray();

        $files = [];

        try {
            foreach ($report->exports() as $key) {
                if (! DatasetRegistry::has($key)) {
                    continue;
                }

                $files[] = $writer->write($datasets->build($key, $filters), $schedule->format);
            }

            Mail::to($schedule->recipients)->send(new ScheduledReportMail(
                tenantName: $schedule->tenant->name,
                reportLabel: $report->label(),
                periodLabel: $filters->period->fromDate().' to '.$filters->period->toDate(),
                summary: $payload,
                files: $files,
                url: url('/reports/'.$report->slug()),
            ));

            $schedule->forceFill(['last_sent_at' => now()])->save();

            activity('reports')->performedOn($schedule)
                ->withProperties(['report' => $report->key(), 'recipients' => count($schedule->recipients)])
                ->log('report.delivered');
        } finally {
            // The attachments only need to survive the send.
            foreach ($files as $file) {
                if (is_file($file['path'])) {
                    Storage::disk('local')->delete(str_replace(Storage::disk('local')->path(''), '', $file['path']));
                }
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Scheduled report delivery failed.', [
            'schedule' => $this->scheduleId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function filtersFor(ReportSchedule $schedule): WidgetFilters
    {
        $stored = $schedule->filters ?? [];
        $timezone = $schedule->tenant->timezone;

        // A schedule stores a preset, not fixed dates: "last 30 days" has to
        // mean the 30 days before this send, not before the day it was created.
        return WidgetFilters::forSnapshot(
            Period::fromPreset($stored['preset'] ?? 'last_30_days', $timezone, $schedule->tenant->fiscal_year_start),
            $stored['channel'] ?? 'all',
        );
    }
}
