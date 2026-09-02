<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class BluedartConnector extends StubConnector
{
    public function id(): string
    {
        return 'bluedart';
    }

    public function label(): string
    {
        return 'Blue Dart';
    }

    public function summary(): string
    {
        return 'Waybill tracking and delivery performance from Blue Dart.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['shipments'];
    }
}
