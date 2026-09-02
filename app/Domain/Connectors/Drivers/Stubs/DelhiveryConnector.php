<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class DelhiveryConnector extends StubConnector
{
    public function id(): string
    {
        return 'delhivery';
    }

    public function label(): string
    {
        return 'Delhivery';
    }

    public function summary(): string
    {
        return 'Waybill tracking, delivery status, RTO and pincode serviceability.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['shipments', 'serviceability'];
    }
}
