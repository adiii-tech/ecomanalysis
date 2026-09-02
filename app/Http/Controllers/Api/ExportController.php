<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Exports\DatasetSheet;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Facades\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One export endpoint for every table in the product.
 *
 * The dataset is built from the same query object the on-screen widget uses, so
 * a download can never disagree with what the user was looking at. Every export
 * is permission-gated on the widget's own `.export` permission and written to
 * the audit log, because an export is data leaving the building.
 */
class ExportController extends Controller
{
    use ResolvesFilters;

    /**
     * A PDF is for reading, not for bulk data — dompdf holds the whole table in
     * memory and 400+ wide rows exhausts it. CSV and XLSX carry the full set,
     * and the PDF says plainly that it was truncated.
     */
    private const PDF_ROW_LIMIT = 150;

    public function index(Request $request): JsonResponse
    {
        $available = collect(DatasetRegistry::catalogue())
            ->filter(fn (array $meta): bool => $request->user()->can($meta['permission']))
            ->map(fn (array $meta, string $key): array => ['key' => $key, 'label' => $meta['label']])
            ->values();

        return ApiResponse::ok(['datasets' => $available->all()]);
    }

    public function download(Request $request, string $dataset, string $format, DatasetRegistry $registry): Response|BinaryFileResponse|StreamedResponse|JsonResponse
    {
        if (! DatasetRegistry::has($dataset)) {
            return ApiResponse::error("Unknown dataset [{$dataset}].", 404);
        }

        $permission = DatasetRegistry::permissionFor($dataset);

        if (! $request->user()->can($permission)) {
            return ApiResponse::error('You do not have permission to export this.', 403, ['required_permission' => [$permission]]);
        }

        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            return ApiResponse::error("Unsupported format [{$format}].", 422);
        }

        $filters = $this->filters($request);
        $built = $registry->build($dataset, $filters);

        activity('export')
            ->withProperties([
                'dataset' => $dataset,
                'format' => $format,
                'rows' => $built->rows->count(),
                'period' => $filters->period->toArray(),
                'channel' => $filters->channelScope,
            ])
            ->log('export.downloaded');

        if ($format === 'pdf') {
            $rows = $built->rows->take(self::PDF_ROW_LIMIT);

            return Pdf::loadView('exports.dataset', [
                'dataset' => $built,
                'rows' => $rows,
                'tenant' => Tenant::current()->name,
                'truncated' => $built->rows->count() > self::PDF_ROW_LIMIT,
                'limit' => self::PDF_ROW_LIMIT,
            ])
                ->setPaper('a4', count($built->columns) > 7 ? 'landscape' : 'portrait')
                ->download($built->filename('pdf'));
        }

        return ExcelFacade::download(
            new DatasetSheet($built),
            $built->filename($format),
            $format === 'csv' ? Excel::CSV : Excel::XLSX,
        );
    }
}
