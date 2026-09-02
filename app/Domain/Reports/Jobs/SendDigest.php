<?php

declare(strict_types=1);

namespace App\Domain\Reports\Jobs;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\ReportRegistry;
use App\Domain\Reports\Services\DatasetFileWriter;
use App\Mail\ScheduledReportMail;
use App\Models\NotificationSetting;
use App\Models\Tenant;
use App\Support\Period;
use App\Support\TenantContext;
use App\Support\WidgetFilters;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * The three standing digests: a morning brief, a weekly business review, and
 * the monthly P&L. Each is a real report rendered at send time, not a
 * separately maintained email — so the digest and the app can never diverge.
 */
class SendDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DAILY = 'daily_brief';

    public const WEEKLY = 'weekly_review';

    public const MONTHLY = 'monthly_pnl';

    public function __construct(
        public readonly int $tenantId,
        public readonly string $kind,
    ) {
        $this->onQueue('default');
    }

    public function handle(
        TenantContext $context,
        ReportRegistry $reports,
        DatasetRegistry $datasets,
        DatasetFileWriter $writer,
    ): void {
        $tenant = $context->withoutScope(fn (): ?Tenant => Tenant::query()->find($this->tenantId));

        if ($tenant === null) {
            return;
        }

        $context->set($tenant);

        $settings = NotificationSetting::query()->firstOrNew(['tenant_id' => $tenant->id]);
        $recipients = $settings->{$this->kind.'_recipients'} ?? [];

        if ($recipients === []) {
            return;
        }

        [$slug, $preset, $label] = match ($this->kind) {
            self::DAILY => ['owner-business-review', 'yesterday', 'Morning brief'],
            self::WEEKLY => ['owner-business-review', 'last_7_days', 'Weekly business review'],
            self::MONTHLY => ['pnl-statement', 'last_month', 'Monthly P&L'],
            default => ['owner-business-review', 'last_7_days', 'Business review'],
        };

        $report = $reports->find($slug);

        if ($report === null) {
            return;
        }

        $filters = WidgetFilters::forSnapshot(Period::fromPreset($preset, $tenant->timezone, $tenant->fiscal_year_start));
        $payload = $report->build($filters)->toArray();

        // The morning brief is read on a phone; only the longer digests carry a
        // file worth opening.
        $files = [];

        if ($this->kind !== self::DAILY) {
            foreach (array_slice($report->exports(), 0, 1) as $key) {
                if (DatasetRegistry::has($key)) {
                    $files[] = $writer->write($datasets->build($key, $filters), 'pdf');
                }
            }
        }

        try {
            Mail::to($recipients)->send(new ScheduledReportMail(
                tenantName: $tenant->name,
                reportLabel: $label,
                periodLabel: $filters->period->fromDate().' to '.$filters->period->toDate(),
                summary: $payload,
                files: $files,
                url: url('/reports/'.$report->slug()),
            ));

            $settings->forceFill([
                'tenant_id' => $tenant->id,
                $this->kind.'_sent_at' => now(),
            ])->save();

            activity('reports')->withProperties(['digest' => $this->kind])->log('digest.sent');
        } finally {
            foreach ($files as $file) {
                if (is_file($file['path'])) {
                    Storage::disk('local')->delete(str_replace(Storage::disk('local')->path(''), '', $file['path']));
                }
            }
        }
    }
}
