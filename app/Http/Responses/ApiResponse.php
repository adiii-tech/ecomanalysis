<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\Caveat;
use App\Support\ResponseMeta;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Http\JsonResponse;

/**
 * The single response envelope every API endpoint returns:
 * `{success, statusCode, message, data, meta}`.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function ok(
        mixed $data,
        ?WidgetFilters $filters = null,
        array $meta = [],
        ?Verdict $verdict = null,
        ?Caveat $caveat = null,
        string $message = 'OK',
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'cached_at' => now()->toIso8601String(),
                'period' => $filters?->period->toArray(),
                'prev_period' => $filters?->period->previous()->toArray(),
                'channel' => $filters?->channelScope,
                'returns_basis' => $filters?->returnsBasis,
                'verdict' => $verdict?->toArray(),
                'caveat' => $caveat?->toArray(),
                ...app(ResponseMeta::class)->all(),
                ...$meta,
            ],
        ]);
    }

    /** @param array<string, mixed> $meta */
    public static function error(string $message, int $status = 400, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'statusCode' => $status,
            'message' => $message,
            'data' => null,
            'meta' => $meta,
        ], $status);
    }
}
