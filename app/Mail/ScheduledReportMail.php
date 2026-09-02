<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The scheduled report email. The body leads with the verdict and the headline
 * numbers so the recipient learns something without opening the attachment —
 * a report nobody opens has not been delivered.
 */
class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array{path: string, filename: string}>  $files
     */
    public function __construct(
        public readonly string $tenantName,
        public readonly string $reportLabel,
        public readonly string $periodLabel,
        public readonly array $summary,
        public readonly array $files,
        public readonly string $url = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('%s · %s (%s)', $this->tenantName, $this->reportLabel, $this->periodLabel),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.scheduled-report', with: [
            'url' => $this->url,
            'headline' => $this->summary['verdict']['headline'] ?? null,
            'detail' => $this->summary['verdict']['detail'] ?? null,
            'action' => $this->summary['verdict']['action'] ?? null,
            'kpis' => array_map(static fn (array $kpi): array => [
                'label' => $kpi['label'],
                'value' => match ($kpi['format']) {
                    'currency' => Money::format((int) round($kpi['value'])),
                    'percent' => number_format($kpi['value'], 1).'%',
                    'ratio' => number_format($kpi['value'], 2).'×',
                    'days' => number_format($kpi['value'], 1).' days',
                    default => number_format($kpi['value']),
                },
                'delta' => $kpi['delta_pct'] === null
                    ? null
                    : sprintf('%s%.1f%%', $kpi['delta_pct'] > 0 ? '+' : '', $kpi['delta_pct']),
                'is_good' => $kpi['is_good'],
            ], array_slice($this->summary['kpis'] ?? [], 0, 6)),
            'caveats' => array_map(static fn (array $caveat): string => $caveat['message'], $this->summary['caveats'] ?? []),
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return array_map(
            static fn (array $file): Attachment => Attachment::fromPath($file['path'])->as($file['filename']),
            $this->files,
        );
    }
}
