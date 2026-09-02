<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Support\Caveat;
use App\Support\Verdict;

/**
 * One block of a report page. Reports are assembled from these rather than
 * hand-written per page, so every report gets the same table behaviour,
 * number formatting and verdict treatment for free.
 */
final class Section
{
    public const TABLE = 'table';

    public const LINE = 'line';

    public const BAR = 'bar';

    public const STACKED = 'stacked';

    public const AREA = 'area';

    public const WATERFALL = 'waterfall';

    public const CALLOUTS = 'callouts';

    public const NARRATIVE = 'narrative';

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly array $rows = [],
        public readonly array $columns = [],
        public readonly array $config = [],
        public readonly ?string $subtitle = null,
        public readonly ?Verdict $verdict = null,
        public readonly ?Caveat $caveat = null,
        public readonly ?string $exportKey = null,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, label: string, format?: string, align?: string, tooltip?: string, tone?: string}>  $columns
     */
    public static function table(string $title, array $rows, array $columns, ?string $subtitle = null, ?Verdict $verdict = null, ?Caveat $caveat = null, ?string $exportKey = null, array $config = []): self
    {
        return new self(self::TABLE, $title, $rows, $columns, $config, $subtitle, $verdict, $caveat, $exportKey);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, label: string, format?: string, color?: string, axis?: string}>  $series
     */
    public static function chart(string $type, string $title, array $rows, string $x, array $series, ?string $subtitle = null, ?Verdict $verdict = null, ?Caveat $caveat = null, array $config = []): self
    {
        return new self($type, $title, $rows, [], ['x' => $x, 'series' => $series, ...$config], $subtitle, $verdict, $caveat);
    }

    /**
     * A gross → margin style waterfall. Each step carries its own sign so the
     * chain reads the way the P&L does.
     *
     * @param  list<array{label: string, value: int|float, type?: string, note?: string}>  $steps
     */
    public static function waterfall(string $title, array $steps, ?string $subtitle = null, ?Verdict $verdict = null, ?Caveat $caveat = null): self
    {
        return new self(self::WATERFALL, $title, $steps, [], ['format' => 'currency'], $subtitle, $verdict, $caveat);
    }

    /**
     * @param  list<array{label: string, value: int|float|string, format?: string, tone?: string, note?: string}>  $cards
     */
    public static function callouts(string $title, array $cards, ?string $subtitle = null, ?Verdict $verdict = null): self
    {
        return new self(self::CALLOUTS, $title, $cards, [], [], $subtitle, $verdict);
    }

    /** @param list<string> $paragraphs */
    public static function narrative(string $title, array $paragraphs, ?string $subtitle = null): self
    {
        return new self(self::NARRATIVE, $title, array_map(static fn (string $text): array => ['text' => $text], $paragraphs), [], [], $subtitle);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'rows' => $this->rows,
            'columns' => $this->columns,
            'config' => (object) $this->config,
            'verdict' => $this->verdict?->toArray(),
            'caveat' => $this->caveat?->toArray(),
            'export_key' => $this->exportKey,
        ];
    }
}
