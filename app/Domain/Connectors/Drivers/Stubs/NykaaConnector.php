<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class NykaaConnector extends StubConnector
{
    public function id(): string
    {
        return 'nykaa';
    }

    public function label(): string
    {
        return 'Nykaa';
    }

    public function summary(): string
    {
        return 'Nykaa orders, returns and settlements.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['orders', 'returns', 'settlements'];
    }
}
