<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class ShiprocketConnector extends StubConnector
{
    public function id(): string
    {
        return 'shiprocket';
    }

    public function label(): string
    {
        return 'Shiprocket';
    }

    public function summary(): string
    {
        return 'Shipment tracking, NDR, RTO and COD remittance from Shiprocket.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['shipments', 'ndr', 'cod_remittance'];
    }
}
