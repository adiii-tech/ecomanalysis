<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class MeeshoConnector extends StubConnector
{
    public function id(): string
    {
        return 'meesho';
    }

    public function label(): string
    {
        return 'Meesho';
    }

    public function summary(): string
    {
        return 'Meesho orders, returns and payouts.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['orders', 'returns', 'payouts'];
    }
}
