<?php

declare(strict_types=1);

namespace App\Domain\Reports\Exports;

use App\Support\Verdict;
use Illuminate\Support\Collection;

/**
 * A named, exportable table: its rows, its columns, and the context a reader
 * needs to trust it (which period, which channel, and any caveat about how the
 * numbers were derived).
 */
final class Dataset
{
    /**
     * @param  Collection<int, array<string, mixed>|object>  $rows
     * @param  list<Column>  $columns
     * @param  array<string, string>  $context
     */
    public function __construct(
        public readonly string $title,
        public readonly Collection $rows,
        public readonly array $columns,
        public readonly array $context = [],
        public readonly ?string $caveat = null,
        public readonly ?Verdict $verdict = null,
        public readonly ?string $subtitle = null,
    ) {}

    /** @return list<string> */
    public function headers(): array
    {
        return array_map(static fn (Column $column): string => $column->header, $this->columns);
    }

    /** @return list<list<string|int|float|null>> */
    public function body(): array
    {
        return $this->rows
            ->map(fn (array|object $row): array => array_map(
                static fn (Column $column): string|int|float|null => $column->value($row),
                $this->columns,
            ))
            ->values()
            ->all();
    }

    public function filename(string $extension): string
    {
        return str($this->title)->slug()->toString().'-'.now()->format('Y-m-d').'.'.$extension;
    }
}
