<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers\Stubs;

use App\Domain\Connectors\Support\StubConnector;

class PinterestAdsConnector extends StubConnector
{
    public function id(): string
    {
        return 'pinterest_ads';
    }

    public function label(): string
    {
        return 'Pinterest Ads';
    }

    public function summary(): string
    {
        return 'Campaign spend and conversion performance.';
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['campaigns', 'insights'];
    }
}
