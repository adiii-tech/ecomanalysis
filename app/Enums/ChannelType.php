<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelType: string
{
    case D2c = 'd2c';
    case Marketplace = 'marketplace';

    public function label(): string
    {
        return match ($this) {
            self::D2c => 'D2C',
            self::Marketplace => 'Marketplace',
        };
    }
}
