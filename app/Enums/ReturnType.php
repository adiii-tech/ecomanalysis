<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnType: string
{
    case CustomerReturn = 'customer_return';
    case Rto = 'rto';
    case Exchange = 'exchange';

    public function label(): string
    {
        return match ($this) {
            self::CustomerReturn => 'Customer Return',
            self::Rto => 'RTO',
            self::Exchange => 'Exchange',
        };
    }
}
