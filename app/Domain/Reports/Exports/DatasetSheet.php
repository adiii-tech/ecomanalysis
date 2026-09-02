<?php

declare(strict_types=1);

namespace App\Domain\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Spreadsheet form of a dataset.
 *
 * The period, channel and any caveat are written above the table, because a
 * file that leaves the app loses all the context the screen had around it — and
 * a returns number means nothing without knowing which basis produced it.
 */
class DatasetSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly Dataset $dataset) {}

    /** @return list<list<string|int|float|null>> */
    public function array(): array
    {
        return $this->dataset->body();
    }

    /** @return list<list<string>|list<string|int|float|null>> */
    public function headings(): array
    {
        $preamble = [[$this->dataset->title]];

        foreach ($this->dataset->context as $label => $value) {
            $preamble[] = [$label.': '.$value];
        }

        if ($this->dataset->caveat !== null) {
            $preamble[] = ['Note: '.$this->dataset->caveat];
        }

        if ($this->dataset->verdict !== null) {
            $preamble[] = [trim($this->dataset->verdict->headline.' '.($this->dataset->verdict->detail ?? ''))];
        }

        $preamble[] = [''];
        $preamble[] = $this->dataset->headers();

        return $preamble;
    }

    /** @return array<int|string, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $headerRow = count($this->headings());

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            $headerRow => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return mb_substr($this->dataset->title, 0, 31);
    }
}
