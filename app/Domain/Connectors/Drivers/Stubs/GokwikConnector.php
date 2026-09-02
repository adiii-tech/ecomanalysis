<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class GokwikConnector extends StubConnector
{
    public function id(): string
    {
        return 'gokwik';
    }

    public function label(): string
    {
        return 'GoKwik';
    }

    public function summary(): string
    {
        return 'One-click checkout conversions, COD verification and RTO scoring.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['checkouts', 'rto_scores'];
    }
}
