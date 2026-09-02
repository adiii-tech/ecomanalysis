<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Manifested = 'manifested';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Ndr = 'ndr';
    case Rto = 'rto';
    case RtoDelivered = 'rto_delivered';
    case Cancelled = 'cancelled';
    case Lost = 'lost';

    public function label(): string
    {
        return str(str_replace('_', ' ', $this->value))->title()->toString();
    }

    public function isInTransit(): bool
    {
        return in_array($this, [self::Manifested, self::InTransit, self::OutForDelivery, self::Ndr], true);
    }
}
