<?php

declare(strict_types=1);

namespace App\Support;

/**
 * All money in this application is stored as integer paise (1 rupee = 100 paise).
 * Never use floats for money — convert at the edge only.
 */
final class Money
{
    public const SCALE = 100;

    public static function fromRupees(float|int|string $rupees): int
    {
        return (int) round(((float) $rupees) * self::SCALE);
    }

    public static function toRupees(int $paise): float
    {
        return $paise / self::SCALE;
    }

    /**
     * Indian grouping: ₹9,56,832.
     */
    public static function format(int $paise, bool $withSymbol = true, int $decimals = 0): string
    {
        $negative = $paise < 0;
        $rupees = abs($paise) / self::SCALE;

        $whole = (string) (int) floor($rupees);
        $fraction = $decimals > 0
            ? '.'.str_pad((string) (int) round(fmod($rupees, 1) * (10 ** $decimals)), $decimals, '0', STR_PAD_LEFT)
            : '';

        $grouped = self::groupIndian($whole);

        return ($negative ? '-' : '').($withSymbol ? '₹' : '').$grouped.$fraction;
    }

    /**
     * Compact Indian notation: ₹9.6L, ₹1.2Cr.
     */
    public static function compact(int $paise, bool $withSymbol = true): string
    {
        $negative = $paise < 0;
        $rupees = abs($paise) / self::SCALE;
        $symbol = $withSymbol ? '₹' : '';

        $formatted = match (true) {
            $rupees >= 10_000_000 => self::trim($rupees / 10_000_000).'Cr',
            $rupees >= 100_000 => self::trim($rupees / 100_000).'L',
            $rupees >= 1_000 => self::trim($rupees / 1_000).'K',
            default => self::trim($rupees),
        };

        return ($negative ? '-' : '').$symbol.$formatted;
    }

    private static function trim(float $value): string
    {
        $rounded = round($value, 1);

        return $rounded == floor($rounded) ? (string) (int) $rounded : (string) $rounded;
    }

    private static function groupIndian(string $whole): string
    {
        if (strlen($whole) <= 3) {
            return $whole;
        }

        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;

        return $rest.','.$last3;
    }

    /**
     * Safe percentage of a paise amount, returned in paise.
     */
    public static function percentOf(int $paise, float $percent): int
    {
        return (int) round($paise * $percent / 100);
    }
}
