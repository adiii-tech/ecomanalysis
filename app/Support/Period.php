<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * An inclusive date range plus its automatically derived comparison period.
 */
final class Period
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $timezone = 'Asia/Kolkata',
        public readonly ?string $preset = null,
    ) {}

    public static function make(?string $from, ?string $to, string $timezone = 'Asia/Kolkata', ?string $preset = null): self
    {
        $end = $to !== null && $to !== ''
            ? CarbonImmutable::parse($to, $timezone)->endOfDay()
            : CarbonImmutable::now($timezone)->endOfDay();

        $start = $from !== null && $from !== ''
            ? CarbonImmutable::parse($from, $timezone)->startOfDay()
            : $end->subDays(29)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return new self($start, $end, $timezone, $preset);
    }

    public static function fromPreset(string $preset, string $timezone = 'Asia/Kolkata', int $fiscalYearStartMonth = 4): self
    {
        $now = CarbonImmutable::now($timezone);

        [$start, $end] = match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'last_30_days' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'last_90_days' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'mtd' => [$now->startOfMonth(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'qtd' => [$now->firstOfQuarter(), $now->endOfDay()],
            'ytd' => [self::fiscalYearStart($now, $fiscalYearStartMonth), $now->endOfDay()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };

        return new self($start, $end, $timezone, $preset);
    }

    private static function fiscalYearStart(CarbonImmutable $now, int $month): CarbonImmutable
    {
        $candidate = $now->setMonth($month)->startOfMonth();

        return $candidate->greaterThan($now) ? $candidate->subYear() : $candidate;
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * The immediately preceding window of identical length.
     */
    public function previous(): self
    {
        $length = $this->days();
        $end = $this->from->subDay()->endOfDay();
        $start = $end->subDays($length - 1)->startOfDay();

        return new self($start, $end, $this->timezone, $this->preset);
    }

    /** @return Collection<int, string> */
    public function dateKeys(): Collection
    {
        $dates = collect();
        for ($cursor = $this->from->startOfDay(); $cursor->lessThanOrEqualTo($this->to); $cursor = $cursor->addDay()) {
            $dates->push($cursor->toDateString());
        }

        return $dates;
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    public function cacheKey(): string
    {
        return $this->fromDate().'_'.$this->toDate();
    }

    /** @return array{from: string, to: string, days: int, preset: string|null} */
    public function toArray(): array
    {
        return [
            'from' => $this->fromDate(),
            'to' => $this->toDate(),
            'days' => $this->days(),
            'preset' => $this->preset,
        ];
    }
}
