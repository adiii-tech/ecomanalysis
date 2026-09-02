<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Reports\Exports\Column;
use App\Domain\Reports\Exports\Dataset;
use App\Support\Caveat;
use App\Support\Metric;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * A report page. Each concrete report declares what it is and assembles its
 * payload from the existing domain queries — no report owns its own SQL, so a
 * number on a report page can never disagree with the same number on a
 * dashboard widget.
 */
abstract class Report
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function category(): string;

    abstract public function description(): string;

    abstract public function build(WidgetFilters $filters): ReportPayload;

    public function slug(): string
    {
        return str_replace('_', '-', $this->key());
    }

    public function permission(): string
    {
        return sprintf('reports.%s.view', $this->key());
    }

    /**
     * Dataset keys from the export registry this report can be downloaded as.
     *
     * @return list<string>
     */
    public function exports(): array
    {
        return [];
    }

    /**
     * Reports read the same rollups the dashboard does, so most of them are
     * period-sensitive; the few that are point-in-time (stock on hand, order
     * aging) say so and the UI hides the date range.
     */
    public function usesPeriod(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return [
            'key' => $this->key(),
            'slug' => $this->slug(),
            'label' => $this->label(),
            'category' => $this->category(),
            'description' => $this->description(),
            'permission' => $this->permission(),
            'exports' => $this->exports(),
            'uses_period' => $this->usesPeriod(),
        ];
    }

    /**
     * @param  'currency'|'number'|'percent'|'ratio'|'days'|'seconds'  $format
     */
    protected function kpi(
        string $key,
        string $label,
        float $value,
        ?float $previous = null,
        string $format = 'currency',
        bool $higherIsBetter = true,
        ?string $tooltip = null,
        ?string $caveat = null,
    ): Metric {
        return new Metric(
            key: $key,
            label: $label,
            value: $value,
            previousValue: $previous,
            format: $format,
            higherIsBetter: $higherIsBetter,
            tooltip: $tooltip,
            caveat: $caveat,
        );
    }

    /** @param array<string, mixed> $totals */
    protected function marginPct(array $totals): float
    {
        return Num::pct((int) ($totals['contribution_margin'] ?? 0), (int) ($totals['net_sales'] ?? 0));
    }

    /** @param list<array<string, mixed>> $rows */
    protected function sumRows(array $rows, string $column): int
    {
        return (int) array_sum(array_map(static fn (array $row): int|float => $row[$column] ?? 0, $rows));
    }

    /**
     * Renders an export dataset as an on-screen table. Reports and their CSV /
     * PDF downloads therefore come from one definition — what you read is
     * exactly what you download, column for column.
     *
     * @param  array{dimension: string, value_key: string, label_key?: string}|null  $drilldown
     */
    protected function tableFromDataset(
        Dataset $dataset,
        ?string $title = null,
        ?Verdict $verdict = null,
        ?string $exportKey = null,
        ?string $subtitle = null,
        ?array $drilldown = null,
    ): Section {
        return Section::table(
            title: $title ?? $dataset->title,
            rows: $dataset->rows->map(static fn (array|object $row): array => (array) $row)->values()->all(),
            columns: array_map(static fn (Column $column): array => [
                'key' => $column->key,
                'label' => $column->header,
                'format' => self::columnFormat($column->format),
                'align' => in_array($column->format, ['money', 'percent', 'number'], true) ? 'right' : 'left',
                'tooltip' => $column->note,
            ], $dataset->columns),
            subtitle: $subtitle ?? $dataset->subtitle,
            verdict: $verdict ?? $dataset->verdict,
            caveat: $dataset->caveat !== null ? Caveat::note($dataset->caveat) : null,
            exportKey: $exportKey,
            // A row that names a channel, state or SKU can open the orders
            // behind it, so no number on a report is a dead end.
            config: $drilldown === null ? [] : ['drilldown' => $drilldown],
        );
    }

    private static function columnFormat(string $format): string
    {
        return match ($format) {
            'money' => 'currency',
            'percent' => 'percent',
            'number' => 'number',
            'date' => 'date',
            'datetime' => 'datetime',
            default => 'text',
        };
    }

    /**
     * Queries return their caveat either as a plain string or as an already
     * serialised Caveat, depending on how they were written. Both are accepted
     * here so a report never silently drops a disclosure.
     */
    protected function caveatFrom(mixed $value): ?Caveat
    {
        if (is_string($value) && $value !== '') {
            return Caveat::note($value);
        }

        if (is_array($value) && isset($value['message'])) {
            return new Caveat((string) $value['message'], (string) ($value['level'] ?? 'info'), $value['connector'] ?? null);
        }

        return null;
    }
}
