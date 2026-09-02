<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class TallyConnector extends StubConnector
{
    public function id(): string
    {
        return 'tally';
    }

    public function label(): string
    {
        return 'Tally';
    }

    public function summary(): string
    {
        return 'Export sales, returns and GST data in Tally-compatible format.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['ledgers', 'vouchers'];
    }
}
