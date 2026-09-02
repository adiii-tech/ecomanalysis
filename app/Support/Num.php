<?php

declare(strict_types=1);

namespace App\Support;

final class Num
{
    public static function safeDivide(float|int $numerator, float|int $denominator, float $fallback = 0.0): float
    {
        if (abs((float) $denominator) < 0.0000001) {
            return $fallback;
        }

        return (float) $numerator / (float) $denominator;
    }

    public static function pct(float|int $part, float|int $whole, int $precision = 2): float
    {
        return round(self::safeDivide($part, $whole) * 100, $precision);
    }

    public static function ratio(float|int $numerator, float|int $denominator, int $precision = 2): float
    {
        return round(self::safeDivide($numerator, $denominator), $precision);
    }

    /**
     * Population standard deviation.
     *
     * @param  list<float|int>  $values
     */
    public static function stdDev(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(static fn ($v): float => (((float) $v) - $mean) ** 2, $values)) / $count;

        return sqrt($variance);
    }

    /** @param list<float|int> $values */
    public static function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /** @param list<float|int> $values */
    public static function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ((float) $values[$middle - 1] + (float) $values[$middle]) / 2
            : (float) $values[$middle];
    }
}
