<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectorStatus: string
{
    case Disconnected = 'disconnected';
    case Connected = 'connected';
    case Error = 'error';
    case Syncing = 'syncing';
    // Authorised, but still missing a choice the user has to make (which ad
    // account, which GA4 property) before it can sync anything meaningful.
    case NeedsSetup = 'needs_setup';

    public function dot(): string
    {
        return match ($this) {
            self::Connected => 'green',
            self::Syncing => 'blue',
            self::Error => 'red',
            self::NeedsSetup => 'amber',
            self::Disconnected => 'grey',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NeedsSetup => 'Needs setup',
            default => ucfirst($this->value),
        };
    }
}
