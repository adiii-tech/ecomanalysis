<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Rto = 'rto';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Placed',
            self::Confirmed => 'Confirmed',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Returned => 'Returned',
            self::Rto => 'RTO',
        };
    }

    /**
     * Statuses that count towards invoiced sales (i.e. the order was actually billed).
     *
     * @return list<string>
     */
    public static function invoicedValues(): array
    {
        return [
            self::Confirmed->value,
            self::Shipped->value,
            self::Delivered->value,
            self::Returned->value,
            self::Rto->value,
        ];
    }

    public function isTerminalLoss(): bool
    {
        return in_array($this, [self::Cancelled, self::Returned, self::Rto], true);
    }
}
