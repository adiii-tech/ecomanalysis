<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Queries\RestockQuery;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The restock desk. Every setting arrives on the request rather than being
 * stored, so a buyer can argue with a suggested quantity by moving the lever
 * that produced it and watching the whole page answer.
 */
class RestockController extends Controller
{
    public function index(Request $request, RestockQuery $restock): JsonResponse
    {
        $validated = $request->validate([
            'window' => ['nullable', Rule::in([30, 60, 90, 180, 365])],
            'lead' => ['nullable', 'integer', 'min:0', 'max:365'],
            'safety' => ['nullable', 'integer', 'min:0', 'max:365'],
            'target' => ['nullable', 'integer', 'min:1', 'max:365'],
            'over' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'dead' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'new_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'round' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'projection' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'best' => ['nullable', Rule::in(['all', '365', '180', '90', '30'])],
            'exclude' => ['nullable', 'string', 'max:200'],
            'returns' => ['nullable', Rule::in(['net', 'gross'])],
            'cost_mode' => ['nullable', Rule::in(['flat', 'percent'])],
            'cost_value' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        return ApiResponse::ok($restock->handle([
            'window' => (int) ($validated['window'] ?? 90),
            'lead' => (int) ($validated['lead'] ?? 21),
            'safety' => (int) ($validated['safety'] ?? 14),
            'target' => (int) ($validated['target'] ?? 60),
            'over' => (int) ($validated['over'] ?? 150),
            'dead' => (int) ($validated['dead'] ?? 90),
            'newDays' => (int) ($validated['new_days'] ?? 30),
            'round' => (int) ($validated['round'] ?? 1),
            'projPerDay' => (float) ($validated['projection'] ?? 0),
            'best' => (string) ($validated['best'] ?? 'all'),
            'exclude' => $this->keywords($validated['exclude'] ?? 'stack, combo'),
            'netReturns' => ($validated['returns'] ?? 'net') === 'net',
            'costMode' => (string) ($validated['cost_mode'] ?? 'flat'),
            'costValue' => (float) ($validated['cost_value'] ?? 150),
        ]));
    }

    /**
     * One SKU's sales history, for the detail drawer. Kept off the list payload
     * on purpose: twelve months per SKU across a whole catalogue is a lot of
     * rows to send for a drawer most of them will never open.
     */
    public function sku(Request $request, RestockQuery $restock, int $sku): JsonResponse
    {
        $netReturns = $request->string('returns')->toString() !== 'gross';

        return ApiResponse::ok($restock->history($sku, $netReturns));
    }

    /** @return list<string> */
    private function keywords(string $raw): array
    {
        return array_values(array_filter(array_map(
            static fn (string $word): string => strtolower(trim($word)),
            explode(',', $raw),
        )));
    }
}
