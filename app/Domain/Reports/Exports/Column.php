<?php

declare(strict_types=1);

namespace App\Domain\Reports\Exports;

use App\Support\Money;

/**
 * One column of an export. `format` decides how the value is rendered in the
 * file — money is stored as integer paise everywhere, so it has to become
 * rupees on the way out or the spreadsheet is off by a factor of 100.
 */
final class Column
{
    public function __construct(
        public readonly string $key,
        public readonly string $header,
        public readonly string $format = 'text',
        public readonly ?string $note = null,
    ) {}

    public static function text(string $key, string $header): self
    {
        return new self($key, $header);
    }

    public static function money(string $key, string $header): self
    {
        return new self($key, $header, 'money');
    }

    public static function number(string $key, string $header): self
    {
        return new self($key, $header, 'number');
    }

    public static function percent(string $key, string $header): self
    {
        return new self($key, $header, 'percent');
    }

    public static function date(string $key, string $header): self
    {
        return new self($key, $header, 'date');
    }

    public static function datetime(string $key, string $header): self
    {
        return new self($key, $header, 'datetime');
    }

    /** @param array<string, mixed>|object $row */
    public function value(array|object $row): string|int|float|null
    {
        $raw = data_get($row, $this->key);

        if ($raw === null) {
            return null;
        }

        return match ($this->format) {
            // Spreadsheets want a real number, not a formatted string, so the
            // recipient can sum and pivot it. Paise become rupees here.
            'money' => round(((int) $raw) / Money::SCALE, 2),
            'number' => is_numeric($raw) ? (float) $raw + 0 : $raw,
            'percent' => round((float) $raw, 2),
            'date' => $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d') : substr((string) $raw, 0, 10),
            'datetime' => $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d H:i') : (string) $raw,
            default => is_scalar($raw) ? (string) $raw : json_encode($raw),
        };
    }

    /** Human-readable value, for PDF where everything is a string anyway. */
    public function display(array|object $row): string
    {
        $raw = data_get($row, $this->key);

        if ($raw === null) {
            return '—';
        }

        return match ($this->format) {
            'money' => Money::format((int) $raw),
            'percent' => number_format((float) $raw, 2).'%',
            'number' => is_numeric($raw) ? number_format((float) $raw) : (string) $raw,
            default => (string) $this->value($row),
        };
    }
}
