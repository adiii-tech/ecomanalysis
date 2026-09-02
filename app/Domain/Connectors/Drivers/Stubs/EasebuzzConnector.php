<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class EasebuzzConnector extends StubConnector
{
    public function id(): string
    {
        return 'easebuzz';
    }

    public function label(): string
    {
        return 'Easebuzz';
    }

    public function summary(): string
    {
        return 'Payment transactions and settlement reports.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['payments', 'settlements'];
    }
}
