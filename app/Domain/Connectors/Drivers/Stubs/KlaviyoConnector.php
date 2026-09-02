<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class KlaviyoConnector extends StubConnector
{
    public function id(): string
    {
        return 'klaviyo';
    }

    public function label(): string
    {
        return 'Klaviyo';
    }

    public function summary(): string
    {
        return 'Email and SMS flows, campaign revenue and audience sync.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['campaigns', 'flows', 'lists'];
    }
}
