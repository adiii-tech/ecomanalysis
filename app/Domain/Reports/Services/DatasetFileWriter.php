<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Exports\Dataset;
use App\Domain\Reports\Exports\DatasetSheet;
use App\Support\Facades\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

/**
 * Writes a dataset to a file on disk, for anything that needs the export
 * without an HTTP response behind it — scheduled email delivery, mainly.
 * The HTTP download and the emailed attachment are produced from the same
 * dataset, so they cannot disagree.
 */
class DatasetFileWriter
{
    /** A PDF is for reading; dompdf exhausts memory on wide, long tables. */
    public const PDF_ROW_LIMIT = 150;

    /** @return array{path: string, filename: string} */
    public function write(Dataset $dataset, string $format): array
    {
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            throw new InvalidArgumentException("Unsupported format [{$format}].");
        }

        $filename = $dataset->filename($format);
        $relative = 'exports/'.uniqid('report-', true).'.'.$format;

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.dataset', [
                'dataset' => $dataset,
                'rows' => $dataset->rows->take(self::PDF_ROW_LIMIT),
                'tenant' => Tenant::current()->name,
                'truncated' => $dataset->rows->count() > self::PDF_ROW_LIMIT,
                'limit' => self::PDF_ROW_LIMIT,
            ])->setPaper('a4', count($dataset->columns) > 7 ? 'landscape' : 'portrait');

            Storage::disk('local')->put($relative, $pdf->output());

            return ['path' => Storage::disk('local')->path($relative), 'filename' => $filename];
        }

        ExcelFacade::store(
            new DatasetSheet($dataset),
            $relative,
            'local',
            $format === 'csv' ? Excel::CSV : Excel::XLSX,
        );

        return ['path' => Storage::disk('local')->path($relative), 'filename' => $filename];
    }
}
