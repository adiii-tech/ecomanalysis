<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthType: string
{
    case OAuth = 'oauth';
    case Token = 'token';
    case KeySecret = 'key_secret';

    public function label(): string
    {
        return match ($this) {
            self::OAuth => 'OAuth',
            self::Token => 'API Token',
            self::KeySecret => 'Key & Secret',
        };
    }
}
