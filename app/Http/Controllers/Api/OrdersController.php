<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Sales\Queries\OrderQuery;
use App\Enums\OrderStatus;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The dedicated Orders page: every order behind the numbers on Dashboard and
 * Marketplace, in one browsable list. Row-level detail is served by
 * {@see DrilldownController::order()} rather than duplicated here — a widget's
 * drilldown and this page open the exact same detail on purpose, so the two
 * screens never disagree about what an order looked like.
 */
class OrdersController extends Controller
{
    use ResolvesFilters;

    public function index(Request $request, OrderQuery $orders): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'sort' => ['nullable', 'string', 'max:32'],
            'direction' => ['nullable', 'in:asc,desc'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $filters = $this->filters($request);

        $data = $orders->list(
            $filters,
            (int) ($validated['limit'] ?? 500),
            (string) ($validated['sort'] ?? 'placed_at'),
            (string) ($validated['direction'] ?? 'desc'),
            $validated['status'] ?? null,
        );

        return ApiResponse::ok($data, $filters);
    }
}
