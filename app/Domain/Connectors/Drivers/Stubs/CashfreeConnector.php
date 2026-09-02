<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class CashfreeConnector extends StubConnector
{
    public function id(): string
    {
        return 'cashfree';
    }

    public function label(): string
    {
        return 'Cashfree';
    }

    public function summary(): string
    {
        return 'Payments, refunds and settlement reconciliation.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['payments', 'refunds', 'settlements'];
    }
}
