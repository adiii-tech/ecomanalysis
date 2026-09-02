<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Channel;
use Illuminate\Http\Request;

/**
 * The filter set every analytics endpoint accepts. Built once per request and
 * passed down, so `from`, `to`, channel and the returns basis are applied
 * identically everywhere.
 */
final class WidgetFilters
{
    public const BASIS_RETURN_DATE = 'return_date';

    public const BASIS_ORDER_DATE = 'order_date';

    /** @param list<int> $channelIds */
    public function __construct(
        public readonly Period $period,
        public readonly array $channelIds = [],
        public readonly string $channelScope = 'all',
        public readonly ?string $paymentMode = null,
        public readonly string $returnsBasis = self::BASIS_ORDER_DATE,
        public readonly ?string $search = null,
    ) {}

    public static function fromRequest(Request $request, string $timezone = 'Asia/Kolkata', int $fiscalYearStart = 4): self
    {
        $preset = $request->string('preset')->toString();

        $period = $preset !== ''
            ? Period::fromPreset($preset, $timezone, $fiscalYearStart)
            : Period::make($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null, $timezone);

        $scope = $request->string('channel', 'all')->toString();

        return new self(
            period: $period,
            channelIds: self::resolveChannelIds($scope),
            channelScope: $scope,
            paymentMode: in_array($request->string('payment_mode')->toString(), ['cod', 'prepaid'], true)
                ? $request->string('payment_mode')->toString()
                : null,
            returnsBasis: $request->string('returns_basis')->toString() === self::BASIS_RETURN_DATE
                ? self::BASIS_RETURN_DATE
                : self::BASIS_ORDER_DATE,
            search: $request->string('q')->toString() ?: null,
        );
    }

    /**
     * Rebuilds a filter set from stored values — a shared report snapshot, a
     * saved view, a schedule — resolving the channel scope the same way a live
     * request would.
     */
    public static function forSnapshot(
        Period $period,
        string $channelScope = 'all',
        ?string $paymentMode = null,
        string $returnsBasis = self::BASIS_ORDER_DATE,
    ): self {
        return new self(
            period: $period,
            channelIds: self::resolveChannelIds($channelScope),
            channelScope: $channelScope,
            paymentMode: $paymentMode,
            returnsBasis: $returnsBasis,
        );
    }

    /** @return list<int> */
    private static function resolveChannelIds(string $scope): array
    {
        if ($scope === '' || $scope === 'all') {
            return [];
        }

        $query = Channel::query();

        return match ($scope) {
            'd2c' => $query->where('type', 'd2c')->pluck('id')->all(),
            'marketplace' => $query->where('type', 'marketplace')->pluck('id')->all(),
            default => $query->where('code', $scope)->pluck('id')->all(),
        };
    }

    public function previous(): self
    {
        return new self(
            $this->period->previous(),
            $this->channelIds,
            $this->channelScope,
            $this->paymentMode,
            $this->returnsBasis,
            $this->search,
        );
    }

    public function withPeriod(Period $period): self
    {
        return new self($period, $this->channelIds, $this->channelScope, $this->paymentMode, $this->returnsBasis, $this->search);
    }

    public function usesReturnDateBasis(): bool
    {
        return $this->returnsBasis === self::BASIS_RETURN_DATE;
    }

    /** @return array<string, mixed> */
    public function cacheKey(): array
    {
        return [
            'from' => $this->period->fromDate(),
            'to' => $this->period->toDate(),
            'channel' => $this->channelScope,
            'basis' => $this->returnsBasis,
            'payment' => $this->paymentMode ?? 'all',
            'q' => $this->search,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'period' => $this->period->toArray(),
            'prev_period' => $this->period->previous()->toArray(),
            'channel' => $this->channelScope,
            'payment_mode' => $this->paymentMode,
            'returns_basis' => $this->returnsBasis,
        ];
    }
}
