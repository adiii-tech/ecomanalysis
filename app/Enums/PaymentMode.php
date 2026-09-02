<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMode: string
{
    case Cod = 'cod';
    case Prepaid = 'prepaid';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'COD',
            self::Prepaid => 'Prepaid',
        };
    }
}
